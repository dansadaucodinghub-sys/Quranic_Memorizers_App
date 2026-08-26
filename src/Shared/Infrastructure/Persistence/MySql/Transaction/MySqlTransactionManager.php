<?php

declare(strict_types=1);

namespace Qmdb\Shared\Infrastructure\Persistence\MySql\Transaction;

use Qmdb\Shared\Database\Transaction\RetryDelayStrategy;
use Qmdb\Shared\Database\Transaction\Sleeper;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Database\Transaction\TransactionOptions;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Exception\DeadlockRetryExhaustedException;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Exception\TransactionStateException;
use Throwable;

final class MySqlTransactionManager implements TransactionManager
{
    private int $depth = 0;
    private ?TransactionOptions $activeOptions = null;

    public function __construct(
        private readonly MySqlTransactionDriver $driver,
        private readonly MySqlRetryableTransactionFailureClassifier $classifier,
        private readonly RetryDelayStrategy $delayStrategy,
        private readonly Sleeper $sleeper,
        private readonly TransactionOptions $defaultOptions,
    ) {
    }

    public function transactional(callable $operation, ?TransactionOptions $options = null): mixed
    {
        if ($this->depth > 0) {
            return $this->nested($operation, $options ?? $this->activeOptions);
        }

        $effectiveOptions = $options ?? $this->defaultOptions;
        $policy = $effectiveOptions->retryPolicy();
        for ($attempt = 1; $attempt <= $policy->maximumAttempts(); $attempt++) {
            try {
                $this->driver->begin($effectiveOptions);
                $this->depth = 1;
                $this->activeOptions = $effectiveOptions;
                $result = $operation();
                $this->driver->commit();
                $this->clearState();

                return $result;
            } catch (Throwable $failure) {
                if ($this->depth > 0) {
                    try {
                        $this->driver->rollBack();
                    } catch (Throwable $rollbackFailure) {
                        $this->clearState();
                        throw new TransactionStateException('Transaction rollback failed.', $rollbackFailure);
                    }
                }
                $this->clearState();
                if (!$this->classifier->isRetryable($failure)) {
                    throw $failure;
                }
                if ($attempt === $policy->maximumAttempts()) {
                    throw new DeadlockRetryExhaustedException($failure);
                }
                $this->sleeper->sleepMilliseconds($this->delayStrategy->delayMilliseconds($attempt, $policy));
            }
        }

        throw new TransactionStateException('Transaction retry state is invalid.');
    }

    private function nested(callable $operation, ?TransactionOptions $options): mixed
    {
        if ($this->activeOptions === null || $options === null) {
            throw new TransactionStateException('Nested transaction state is invalid.');
        }
        if (
            $options->isolation() !== $this->activeOptions->isolation()
            || $options->isReadOnly() !== $this->activeOptions->isReadOnly()
        ) {
            throw new TransactionStateException('Nested transaction options are incompatible.');
        }

        $savepoint = 'qmdb_sp_' . $this->depth;
        $this->driver->createSavepoint($savepoint);
        $this->depth++;
        try {
            $result = $operation();
            $this->driver->releaseSavepoint($savepoint);
            $this->depth--;

            return $result;
        } catch (Throwable $failure) {
            try {
                $this->driver->rollBackToSavepoint($savepoint);
                $this->driver->releaseSavepoint($savepoint);
            } finally {
                $this->depth--;
            }
            throw $failure;
        }
    }

    private function clearState(): void
    {
        $this->depth = 0;
        $this->activeOptions = null;
    }
}
