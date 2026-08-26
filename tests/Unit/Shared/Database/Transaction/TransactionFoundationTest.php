<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Database\Transaction;

use PDOException;
use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Database\Transaction\TransactionIsolation;
use Qmdb\Shared\Database\Transaction\TransactionOptions;
use Qmdb\Shared\Database\Transaction\TransactionRetryPolicy;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Exception\DeadlockRetryExhaustedException;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Exception\TransactionStateException;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Transaction\MySqlRetryableTransactionFailureClassifier;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Transaction\MySqlTransactionManager;
use Qmdb\Tests\Support\MySql\DeterministicRetryDelayStrategy;
use Qmdb\Tests\Support\MySql\RecordingSleeper;
use Qmdb\Tests\Support\MySql\RecordingTransactionDriver;
use RuntimeException;

final class TransactionFoundationTest extends TestCase
{
    public function testSuccessfulTransactionReturnsResultAndCommits(): void
    {
        [$manager, $driver] = $this->manager();

        self::assertSame('result', $manager->transactional(static fn (): string => 'result'));
        self::assertSame(['begin:read-write', 'commit'], $driver->events);
    }

    public function testFailureRollsBackAndRethrowsOriginalFailure(): void
    {
        [$manager, $driver] = $this->manager();
        $failure = new RuntimeException('application failure');

        try {
            $manager->transactional(static function () use ($failure): never {
                throw $failure;
            });
        } catch (RuntimeException $caught) {
            self::assertSame($failure, $caught);
            self::assertSame(['begin:read-write', 'rollback'], $driver->events);
        }
    }

    public function testNestedSuccessUsesDeterministicSavepoint(): void
    {
        [$manager, $driver] = $this->manager();

        $manager->transactional(function () use ($manager): void {
            $manager->transactional(static fn (): null => null);
        });

        self::assertSame(
            ['begin:read-write', 'savepoint:qmdb_sp_1', 'release:qmdb_sp_1', 'commit'],
            $driver->events,
        );
    }

    public function testCaughtNestedFailureRollsBackOnlyToSavepoint(): void
    {
        [$manager, $driver] = $this->manager();

        $manager->transactional(function () use ($manager): void {
            try {
                $manager->transactional(static function (): never {
                    throw new RuntimeException('nested');
                });
            } catch (RuntimeException) {
            }
        });

        self::assertSame(
            [
                'begin:read-write',
                'savepoint:qmdb_sp_1',
                'rollback-to:qmdb_sp_1',
                'release:qmdb_sp_1',
                'commit',
            ],
            $driver->events,
        );
    }

    public function testUncaughtNestedFailureRollsBackOuterTransaction(): void
    {
        [$manager, $driver] = $this->manager();

        try {
            $manager->transactional(function () use ($manager): void {
                $manager->transactional(static function (): never {
                    throw new RuntimeException('nested');
                });
            });
        } catch (RuntimeException) {
        }

        self::assertSame('rollback', array_pop($driver->events));
    }

    public function testIncompatibleNestedOptionsFailBeforeNestedCallback(): void
    {
        [$manager] = $this->manager();
        $this->expectException(TransactionStateException::class);
        $manager->transactional(function () use ($manager): void {
            $manager->transactional(
                static fn (): null => null,
                TransactionOptions::readOnly(),
            );
        });
    }

    public function testRetryableDeadlockRetriesWholeTransactionAfterRollbackAndDelay(): void
    {
        [$manager, $driver, $sleeper] = $this->manager();
        $calls = 0;

        $result = $manager->transactional(function () use (&$calls): string {
            $calls++;
            if ($calls === 1) {
                throw self::deadlock();
            }

            return 'recovered';
        });

        self::assertSame('recovered', $result);
        self::assertSame(2, $calls);
        self::assertSame(['begin:read-write', 'rollback', 'begin:read-write', 'commit'], $driver->events);
        self::assertSame([7], $sleeper->delays);
    }

    public function testNonRetryableFailureDoesNotRetryOrSleep(): void
    {
        [$manager, $driver, $sleeper] = $this->manager();

        try {
            $manager->transactional(static function (): never {
                throw new RuntimeException('validation');
            });
        } catch (RuntimeException) {
        }

        self::assertSame(['begin:read-write', 'rollback'], $driver->events);
        self::assertSame([], $sleeper->delays);
    }

    public function testRetryExhaustionIsSafeBoundedAndHasNoFinalDelay(): void
    {
        [$manager, , $sleeper] = $this->manager(maximumAttempts: 2);
        $calls = 0;

        try {
            $manager->transactional(static function () use (&$calls): never {
                $calls++;
                throw self::deadlock();
            });
        } catch (DeadlockRetryExhaustedException $exception) {
            self::assertSame(2, $calls);
            self::assertCount(1, $sleeper->delays);
            self::assertStringNotContainsString('secret-sql-value', $exception->getMessage());
        }
    }

    public function testStateIsReusableAfterFailure(): void
    {
        [$manager, $driver] = $this->manager();
        try {
            $manager->transactional(static function (): never {
                throw new RuntimeException('first');
            });
        } catch (RuntimeException) {
        }

        self::assertSame('second', $manager->transactional(static fn (): string => 'second'));
        self::assertSame(['begin:read-write', 'rollback', 'begin:read-write', 'commit'], $driver->events);
    }

    public function testTransactionOptionsExposeApprovedIsolationAndMode(): void
    {
        $readWrite = TransactionOptions::readWrite(TransactionIsolation::SERIALIZABLE);
        $readOnly = TransactionOptions::readOnly(TransactionIsolation::REPEATABLE_READ);

        self::assertSame(TransactionIsolation::SERIALIZABLE, $readWrite->isolation());
        self::assertFalse($readWrite->isReadOnly());
        self::assertSame(TransactionIsolation::REPEATABLE_READ, $readOnly->isolation());
        self::assertTrue($readOnly->isReadOnly());
    }

    /** @return array{MySqlTransactionManager, RecordingTransactionDriver, RecordingSleeper} */
    private function manager(int $maximumAttempts = 3): array
    {
        $driver = new RecordingTransactionDriver();
        $sleeper = new RecordingSleeper();
        $policy = new TransactionRetryPolicy($maximumAttempts, 1, 10);
        $manager = new MySqlTransactionManager(
            $driver,
            new MySqlRetryableTransactionFailureClassifier(),
            new DeterministicRetryDelayStrategy(),
            $sleeper,
            TransactionOptions::readWrite(retryPolicy: $policy),
        );

        return [$manager, $driver, $sleeper];
    }

    private static function deadlock(): PDOException
    {
        $failure = new PDOException('deadlock with secret-sql-value', 40001);
        $failure->errorInfo = ['40001', 1213, 'deadlock with secret-sql-value'];

        return $failure;
    }
}
