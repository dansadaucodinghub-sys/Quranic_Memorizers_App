<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Security\Secrets;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Security\Secrets\SecretName;

final class SecretNameTest extends TestCase
{
    public function testCanonicalNameIsAccepted(): void
    {
        self::assertSame('APP_ENCRYPTION_KEY', SecretName::fromString('APP_ENCRYPTION_KEY')->value());
    }

    public function testEmptyNameIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SecretName::fromString('');
    }

    public function testLowercaseNameIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SecretName::fromString('secret_name');
    }

    public function testWhitespaceIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SecretName::fromString('SECRET NAME');
    }

    public function testPathTraversalIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SecretName::fromString('../../SECRET');
    }

    public function testPunctuationAndLeadingDigitAreRejected(): void
    {
        foreach (['SECRET-NAME', '1INVALID'] as $invalid) {
            try {
                SecretName::fromString($invalid);
                self::fail('Invalid secret name must fail.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testEqualityUsesCanonicalValue(): void
    {
        $name = SecretName::fromString('DATABASE_PASSWORD');

        self::assertTrue($name->equals(SecretName::fromString('DATABASE_PASSWORD')));
        self::assertFalse($name->equals(SecretName::fromString('APP_ENCRYPTION_KEY')));
    }
}
