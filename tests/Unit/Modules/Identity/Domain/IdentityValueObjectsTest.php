<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Modules\Identity\Domain;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\Identity\Domain\Value\EmailAddress;
use Qmdb\Modules\Identity\Domain\Value\LookupHash;
use Qmdb\Modules\Identity\Domain\Value\PhoneNumber;
use Qmdb\Modules\Identity\Domain\Value\SensitivePasswordHash;
use Qmdb\Modules\Identity\Infrastructure\Security\SodiumContactCipher;

final class IdentityValueObjectsTest extends TestCase
{
    public function testUuidV7AccountIdRoundTripsWithoutExposingDatabaseIdentity(): void
    {
        $id = AccountId::generate();

        self::assertSame($id->toString(), AccountId::fromString($id->toString())->toString());
        self::assertSame($id->toString(), AccountId::fromBinary($id->toBinary())->toString());
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $id->toString());
    }

    #[DataProvider('emailVectors')]
    public function testEmailNormalizationUsesOneDocumentedProviderNeutralContract(
        string $input,
        string $expected,
    ): void {
        self::assertSame($expected, EmailAddress::fromInput($input)->normalized());
    }

    /** @return iterable<string, array{string, string}> */
    public static function emailVectors(): iterable
    {
        yield 'trim and ASCII case fold' => ['  Person.Example@Example.COM ', 'person.example@example.com'];
        yield 'no provider-specific alias rewrite' => ['person+tag@example.com', 'person+tag@example.com'];
        yield 'dots preserved' => ['first.last@example.com', 'first.last@example.com'];
    }

    #[DataProvider('invalidEmails')]
    public function testInvalidOrUnsupportedEmailInputFailsClosed(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        EmailAddress::fromInput($input);
    }

    /** @return iterable<array{string}> */
    public static function invalidEmails(): iterable
    {
        yield [''];
        yield ['missing-at.example'];
        yield ['δοκιμή@example.com'];
        yield [str_repeat('a', 250) . '@example.com'];
    }

    #[DataProvider('phoneVectors')]
    public function testPhoneNormalizationAcceptsOnlyCanonicalE164(string $input, bool $valid): void
    {
        if (!$valid) {
            $this->expectException(InvalidArgumentException::class);
        }
        $phone = PhoneNumber::fromInput($input);
        if ($valid) {
            self::assertSame(trim($input), $phone->normalized());
        }
    }

    /** @return iterable<array{string, bool}> */
    public static function phoneVectors(): iterable
    {
        yield ['+12025550123', true];
        yield [' +447911123456 ', true];
        yield ['08012345678', false];
        yield ['+1 202 555 0123', false];
        yield ['+0123456789', false];
        yield ['+1234567', false];
        yield ['+1234567890123456', false];
    }

    public function testLookupHashIsPurposeBoundAndRedacted(): void
    {
        $key = random_bytes(32);
        $email = LookupHash::keyed('email', 'person@example.test', $key);
        $phone = LookupHash::keyed('phone', 'person@example.test', $key);

        self::assertNotSame($email->toBinary(), $phone->toBinary());
        self::assertSame(['value' => '[REDACTED]'], $email->__debugInfo());
    }

    public function testSodiumCipherAuthenticatesAndRedactsContactData(): void
    {
        $cipher = new SodiumContactCipher(random_bytes(32), 'test-key-v1');
        $encrypted = $cipher->encrypt('person@example.test');

        self::assertNotSame('person@example.test', $encrypted);
        self::assertSame('person@example.test', $cipher->decrypt($encrypted));
        self::assertSame('test-key-v1', $cipher->keyId());
        self::assertSame('[REDACTED]', $cipher->__debugInfo()['key']);
    }

    public function testPasswordHashCannotLeakThroughJsonOrDebugging(): void
    {
        $hash = new SensitivePasswordHash(password_hash('synthetic-test-password', PASSWORD_ARGON2ID));

        self::assertSame('"[REDACTED]"', json_encode($hash));
        self::assertSame(['value' => '[REDACTED]'], $hash->__debugInfo());
    }
}
