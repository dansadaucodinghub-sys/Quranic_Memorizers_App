<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Background;

use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Background\Job\BackgroundJobEnvelope;
use Qmdb\Shared\Background\Job\BackgroundJobFailureCode;
use Qmdb\Shared\Background\Job\BackgroundJobHandlerMap;
use Qmdb\Shared\Background\Job\BackgroundJobHandlerRegistry;
use Qmdb\Shared\Background\Job\BackgroundJobId;
use Qmdb\Shared\Background\Job\BackgroundJobName;
use Qmdb\Shared\Background\Job\JobReservationToken;
use Qmdb\Shared\Background\Job\ReservedBackgroundJob;
use Qmdb\Shared\Observability\Correlation\CorrelationId;
use Qmdb\Tests\Support\Background\TestBackgroundJob;
use Qmdb\Tests\Support\Background\TestBackgroundJobHandler;
use Qmdb\Tests\Support\Background\ChildBackgroundJob;
use Qmdb\Tests\Support\Background\ParentBackgroundJob;
use Qmdb\Tests\Support\Background\SecondaryBackgroundJob;
use ReflectionMethod;

final class BackgroundJobContractTest extends TestCase
{
    private const NOW = '2026-08-25T09:00:00+02:00';

    public function testEnvelopeNormalizesUtcAndRedactsItsJobFromDebugOutput(): void
    {
        $envelope = $this->envelope();

        self::assertSame('2026-08-25T07:00:00+00:00', $envelope->createdAt()->format(DATE_ATOM));
        self::assertSame('[object:redacted]', $envelope->__debugInfo()['job']);
        self::assertSame(1, $envelope->attempt());
        self::assertSame(3, $envelope->maximumAttempts());
    }

    #[DataProvider('invalidEnvelopeAttempts')]
    public function testEnvelopeRejectsInvalidAttempts(int $attempt, int $maximum): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->envelope($attempt, $maximum);
    }

    /** @return iterable<string, array{int, int}> */
    public static function invalidEnvelopeAttempts(): iterable
    {
        yield 'zero attempt' => [0, 3];
        yield 'above maximum' => [4, 3];
        yield 'zero maximum' => [1, 0];
        yield 'excessive maximum' => [1, 101];
    }

    public function testEnvelopeRejectsAvailabilityBeforeCreation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $now = new DateTimeImmutable(self::NOW);
        new BackgroundJobEnvelope(
            new BackgroundJobId(str_repeat('a', 32)),
            new BackgroundJobName('test.background.job'),
            new TestBackgroundJob(),
            new CorrelationId(str_repeat('b', 32)),
            $now,
            $now->modify('-1 second'),
        );
    }

    public function testReservationTokenIsRedactedAndCannotBeSerialized(): void
    {
        $token = new JobReservationToken('opaque-reservation-token');
        $reserved = new ReservedBackgroundJob($this->envelope(), $token);

        self::assertSame('[REDACTED]', $token->__debugInfo()['reservation_token']);
        self::assertSame('[REDACTED]', $reserved->__debugInfo()['reservation_token']);
        $this->expectException(LogicException::class);
        serialize($token);
    }

    #[DataProvider('invalidValueObjects')]
    public function testInvalidJobValuesAreRejected(string $type, string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        match ($type) {
            'name' => new BackgroundJobName($value),
            'id' => new BackgroundJobId($value),
            'failure' => new BackgroundJobFailureCode($value),
            default => throw new LogicException('Unsupported test value-object type.'),
        };
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidValueObjects(): iterable
    {
        yield 'job name path' => ['name', '../job'];
        yield 'job name class' => ['name', 'App\\SecretJob'];
        yield 'job id' => ['id', '123'];
        yield 'failure message' => ['failure', 'FAILED: secret detail'];
    }

    public function testHandlerRegistryUsesExactClassAndFreezesAfterBuild(): void
    {
        $handler = new TestBackgroundJobHandler();
        $registry = new BackgroundJobHandlerRegistry();
        $map = $registry->register(TestBackgroundJob::class, $handler)->build();

        self::assertSame($handler, $map->handlerFor(new TestBackgroundJob()));
        self::assertSame(1, $map->count());

        $this->expectException(LogicException::class);
        $registry->register(SecondaryBackgroundJob::class, new TestBackgroundJobHandler());
    }

    public function testHandlerRegistryRejectsDuplicateAndNonJobClasses(): void
    {
        $handler = new TestBackgroundJobHandler();
        $registry = new BackgroundJobHandlerRegistry();
        $registry->register(TestBackgroundJob::class, $handler);
        try {
            $registry->register(TestBackgroundJob::class, $handler);
            self::fail('Duplicate registration was accepted.');
        } catch (InvalidArgumentException) {
            self::addToAssertionCount(1);
        }

        $this->expectException(InvalidArgumentException::class);
        (new ReflectionMethod(BackgroundJobHandlerRegistry::class, 'register'))->invoke(
            new BackgroundJobHandlerRegistry(),
            \stdClass::class,
            $handler,
        );
    }

    public function testHandlerMapHasNoParentOrInterfaceFallback(): void
    {
        $map = new BackgroundJobHandlerMap([ParentBackgroundJob::class => new TestBackgroundJobHandler()]);

        $this->expectException(InvalidArgumentException::class);
        $map->handlerFor(new ChildBackgroundJob());
    }

    public function testEmptyHandlerMapIsValid(): void
    {
        self::assertSame(0, (new BackgroundJobHandlerRegistry())->build()->count());
    }

    private function envelope(int $attempt = 1, int $maximum = 3): BackgroundJobEnvelope
    {
        $now = new DateTimeImmutable(self::NOW);

        return new BackgroundJobEnvelope(
            new BackgroundJobId(str_repeat('a', 32)),
            new BackgroundJobName('test.background.job'),
            new TestBackgroundJob(),
            new CorrelationId(str_repeat('b', 32)),
            $now,
            $now,
            $attempt,
            $maximum,
        );
    }
}
