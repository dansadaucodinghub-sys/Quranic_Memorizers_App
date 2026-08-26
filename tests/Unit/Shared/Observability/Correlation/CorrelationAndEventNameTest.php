<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Observability\Correlation;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Identifier\SecureRandomRuntimeIdentifierGenerator;
use Qmdb\Shared\Observability\Correlation\CorrelationId;
use Qmdb\Shared\Observability\Correlation\SecureCorrelationIdGenerator;
use Qmdb\Shared\Observability\Logging\LogEventName;

final class CorrelationAndEventNameTest extends TestCase
{
    public function testSecureGeneratorProducesDistinctCanonicalIdentifiers(): void
    {
        $generator = new SecureCorrelationIdGenerator(new SecureRandomRuntimeIdentifierGenerator());
        $first = $generator->generate();
        $second = $generator->generate();

        self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\z/D', $first->value());
        self::assertSame(16, strlen(hex2bin($first->value()) ?: ''));
        self::assertFalse($first->equals($second));
        self::assertNotSame($first->value(), $second->value());
    }

    public function testCorrelationEqualityIsValueBased(): void
    {
        self::assertTrue((new CorrelationId(str_repeat('a', 32)))->equals(
            new CorrelationId(str_repeat('a', 32)),
        ));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidCorrelationIds(): iterable
    {
        yield 'short' => [str_repeat('a', 31)];
        yield 'long' => [str_repeat('a', 33)];
        yield 'uppercase' => [str_repeat('A', 32)];
        yield 'non hex' => [str_repeat('g', 32)];
        yield 'whitespace' => [str_repeat('a', 31) . ' '];
    }

    #[DataProvider('invalidCorrelationIds')]
    public function testInvalidCorrelationIdIsRejected(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CorrelationId($value);
    }

    public function testEventNameIsCanonicalAndComparable(): void
    {
        $event = new LogEventName('http.request.completed');

        self::assertSame('http.request.completed', $event->value());
        self::assertTrue($event->equals(new LogEventName('http.request.completed')));
        self::assertFalse($event->equals(new LogEventName('http.request.started')));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidEventNames(): iterable
    {
        yield 'empty' => [''];
        yield 'single segment' => ['http'];
        yield 'uppercase' => ['HTTP.request'];
        yield 'underscore' => ['http_request.started'];
        yield 'whitespace' => ['http request.started'];
        yield 'path' => ['../http.request'];
        yield 'duplicate dot' => ['http..request'];
        yield 'too long' => ['event.' . str_repeat('a', 124)];
    }

    #[DataProvider('invalidEventNames')]
    public function testInvalidEventNameIsRejected(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        new LogEventName($value);
    }
}
