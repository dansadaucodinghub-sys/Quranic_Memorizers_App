<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Configuration;

use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Configuration\ConfigurationException;
use Qmdb\Shared\Configuration\ConfigurationViolation;

final class ConfigurationExceptionTest extends TestCase
{
    public function testStructuredViolationsAreRetained(): void
    {
        $violation = $this->violation();
        $exception = new ConfigurationException([$violation]);

        self::assertSame([$violation], $exception->violations());
        self::assertSame(
            [
                'code' => ConfigurationViolation::REQUIRED_VALUE_MISSING,
                'variable' => 'APP_ENV',
                'message' => 'APP_ENV is required.',
                'severity' => 'error',
            ],
            $violation->toSafeArray(),
        );
    }

    public function testCliRenderingIsSafeAndDeterministic(): void
    {
        $exception = new ConfigurationException([$this->violation()]);

        self::assertSame(
            "Application configuration is invalid:\n"
            . '- [CONFIG_REQUIRED_VALUE_MISSING] APP_ENV: APP_ENV is required.',
            $exception->toCliString(),
        );
        self::assertSame($exception->toCliString(), $exception->getMessage());
    }

    public function testSafeMessageContainsNoRawValueOrStackTrace(): void
    {
        $exception = new ConfigurationException([$this->violation()]);

        self::assertStringNotContainsString('RAW_SECRET_VALUE', $exception->getMessage());
        self::assertStringNotContainsString('Stack trace', $exception->getMessage());
        self::assertStringNotContainsString(__DIR__, $exception->getMessage());
    }

    public function testStableViolationCodeIsExposed(): void
    {
        self::assertSame(
            'CONFIG_REQUIRED_VALUE_MISSING',
            $this->violation()->code(),
        );
    }

    private function violation(): ConfigurationViolation
    {
        return new ConfigurationViolation(
            ConfigurationViolation::REQUIRED_VALUE_MISSING,
            'APP_ENV',
            'APP_ENV is required.',
        );
    }
}
