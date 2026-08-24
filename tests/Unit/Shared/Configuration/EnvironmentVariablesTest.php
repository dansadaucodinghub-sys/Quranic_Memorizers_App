<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Configuration;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Configuration\EnvironmentVariables;
use ReflectionClass;

final class EnvironmentVariablesTest extends TestCase
{
    public function testRequiredStringRetrievalPreservesTheValue(): void
    {
        $variables = new EnvironmentVariables(['APP_ENV' => ' local ']);

        self::assertSame(' local ', $variables->requiredString('APP_ENV'));
    }

    public function testOptionalStringDistinguishesMissingAndPresentValues(): void
    {
        $variables = new EnvironmentVariables(['APP_DEBUG' => '']);

        self::assertSame('', $variables->optionalString('APP_DEBUG'));
        self::assertNull($variables->optionalString('APP_TIMEZONE'));
        self::assertTrue($variables->has('APP_DEBUG'));
        self::assertFalse($variables->has('APP_TIMEZONE'));
    }

    public function testMissingRequiredValueFailsSafely(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('APP_ENV is required.');

        (new EnvironmentVariables([]))->requiredString('APP_ENV');
    }

    public function testEmptyRequiredValueFailsSafely(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('APP_ENV is required.');

        (new EnvironmentVariables(['APP_ENV' => '   ']))->requiredString('APP_ENV');
    }

    public function testBooleanUsesTheSuppliedDefaultWhenMissing(): void
    {
        self::assertFalse((new EnvironmentVariables([]))->boolean('APP_DEBUG', false));
        self::assertTrue((new EnvironmentVariables([]))->boolean('APP_DEBUG', true));
    }

    public function testCanonicalTrueValuesAreAccepted(): void
    {
        self::assertTrue((new EnvironmentVariables(['APP_DEBUG' => 'true']))->boolean('APP_DEBUG', false));
        self::assertTrue((new EnvironmentVariables(['APP_DEBUG' => ' 1 ']))->boolean('APP_DEBUG', false));
        self::assertTrue((new EnvironmentVariables(['APP_DEBUG' => 'TRUE']))->boolean('APP_DEBUG', false));
    }

    public function testCanonicalFalseValuesAreAccepted(): void
    {
        self::assertFalse((new EnvironmentVariables(['APP_DEBUG' => 'false']))->boolean('APP_DEBUG', true));
        self::assertFalse((new EnvironmentVariables(['APP_DEBUG' => ' 0 ']))->boolean('APP_DEBUG', true));
        self::assertFalse((new EnvironmentVariables(['APP_DEBUG' => 'FALSE']))->boolean('APP_DEBUG', true));
    }

    public function testAmbiguousBooleanIsRejectedWithoutEchoingTheValue(): void
    {
        try {
            (new EnvironmentVariables(['APP_DEBUG' => 'yes-secret']))->boolean('APP_DEBUG', false);
            self::fail('Ambiguous boolean must fail.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('APP_DEBUG', $exception->getMessage());
            self::assertStringNotContainsString('yes-secret', $exception->getMessage());
        }
    }

    public function testInvalidVariableNameIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EnvironmentVariables(['invalid-name' => 'value']);
    }

    public function testNonStringValueIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EnvironmentVariables(['APP_DEBUG' => true]);
    }

    public function testCollectionIsImmutableAndHasNoRawDumpMethod(): void
    {
        $reflection = new ReflectionClass(EnvironmentVariables::class);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());

        foreach (['all', 'toArray', 'raw', 'values'] as $method) {
            self::assertFalse($reflection->hasMethod($method));
        }
    }

    public function testDebugRepresentationIsRedacted(): void
    {
        $secret = 'QMDB_TEST_SECRET_1d80f253';
        $variables = new EnvironmentVariables(['SAFE_TEST_VALUE' => $secret]);

        ob_start();
        var_dump($variables);
        $output = (string) ob_get_clean();

        self::assertStringContainsString('[REDACTED]', $output);
        self::assertStringNotContainsString($secret, $output);

        try {
            serialize($variables);
            self::fail('Environment-variable serialization must fail.');
        } catch (LogicException) {
            self::addToAssertionCount(1);
        }
    }
}
