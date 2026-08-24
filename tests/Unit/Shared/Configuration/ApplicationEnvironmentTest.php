<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Configuration;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Configuration\ApplicationEnvironment;

final class ApplicationEnvironmentTest extends TestCase
{
    public function testLocalParsesWithSafeSurroundingWhitespace(): void
    {
        self::assertSame(ApplicationEnvironment::LOCAL, ApplicationEnvironment::parse(' local '));
    }

    public function testTestParses(): void
    {
        self::assertSame(ApplicationEnvironment::TEST, ApplicationEnvironment::parse('test'));
    }

    public function testStagingParses(): void
    {
        self::assertSame(ApplicationEnvironment::STAGING, ApplicationEnvironment::parse('staging'));
    }

    public function testProductionParses(): void
    {
        self::assertSame(ApplicationEnvironment::PRODUCTION, ApplicationEnvironment::parse('production'));
    }

    public function testUnsupportedValueIsRejectedWithoutEchoingTheValue(): void
    {
        try {
            ApplicationEnvironment::parse('UNSAFE_RAW_VALUE');
            self::fail('Unsupported environment must fail.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringNotContainsString('UNSAFE_RAW_VALUE', $exception->getMessage());
        }
    }

    public function testProductionLikeClassificationIsExact(): void
    {
        self::assertFalse(ApplicationEnvironment::LOCAL->isProductionLike());
        self::assertFalse(ApplicationEnvironment::TEST->isProductionLike());
        self::assertTrue(ApplicationEnvironment::STAGING->isProductionLike());
        self::assertTrue(ApplicationEnvironment::PRODUCTION->isProductionLike());
    }

    public function testLocalEnvironmentFileEligibilityIsExact(): void
    {
        self::assertTrue(ApplicationEnvironment::LOCAL->allowsLocalEnvironmentFile());
        self::assertTrue(ApplicationEnvironment::TEST->allowsLocalEnvironmentFile());
        self::assertFalse(ApplicationEnvironment::STAGING->allowsLocalEnvironmentFile());
        self::assertFalse(ApplicationEnvironment::PRODUCTION->allowsLocalEnvironmentFile());
    }

    public function testSafeDisplayIsCanonicalAndDeterministic(): void
    {
        self::assertSame('production', ApplicationEnvironment::PRODUCTION->toSafeString());
        self::assertSame('production', ApplicationEnvironment::PRODUCTION->toSafeString());
    }
}
