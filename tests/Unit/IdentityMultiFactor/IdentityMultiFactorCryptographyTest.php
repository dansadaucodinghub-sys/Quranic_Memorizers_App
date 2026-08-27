<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\IdentityMultiFactor;

use DateTimeImmutable;
use OTPHP\TOTP;
use PHPUnit\Framework\TestCase;
use Qmdb\Modules\IdentityMultiFactor\Application\SecureRecoveryCodeGenerator;
use Qmdb\Modules\IdentityMultiFactor\Application\TotpVerifier;
use Qmdb\Modules\IdentityMultiFactor\Configuration\IdentityMultiFactorConfiguration;
use Qmdb\Modules\IdentityMultiFactor\Domain\EncryptedTotpSecret;
use Qmdb\Modules\IdentityMultiFactor\Domain\NormalizedRecoveryCode;
use Qmdb\Modules\IdentityMultiFactor\Domain\TotpSecret;
use Qmdb\Modules\IdentityMultiFactor\Infrastructure\Security\SodiumTotpSecretEncryptor;

final class IdentityMultiFactorCryptographyTest extends TestCase
{
    public function testTotpSecretEncryptionAuthenticatesAccountAndAuthenticatorBinding(): void
    {
        $encryptor = new SodiumTotpSecretEncryptor(random_bytes(32), 3);
        $secret = TotpSecret::generate();
        $encrypted = $encryptor->encrypt($secret, 'account-a', 'authenticator-a');

        self::assertSame(3, $encrypted->keyVersion);
        self::assertSame(
            $secret->revealForTotp(),
            $encryptor->decrypt($encrypted, 'account-a', 'authenticator-a')->revealForTotp(),
        );

        $this->expectException(\RuntimeException::class);
        $encryptor->decrypt($encrypted, 'account-b', 'authenticator-a');
    }

    public function testCiphertextTamperingIsRejected(): void
    {
        $encryptor = new SodiumTotpSecretEncryptor(random_bytes(32), 1);
        $encrypted = $encryptor->encrypt(TotpSecret::generate(), 'account-a', 'authenticator-a');
        $tampered = new EncryptedTotpSecret(
            $encrypted->ciphertext() ^ str_repeat("\x01", strlen($encrypted->ciphertext())),
            $encrypted->nonce(),
            1,
        );

        $this->expectException(\RuntimeException::class);
        $encryptor->decrypt($tampered, 'account-a', 'authenticator-a');
    }

    public function testTotpVerificationAcceptsConfiguredDriftAndRejectsReplay(): void
    {
        $configuration = $this->configuration();
        $secret = TotpSecret::generate();
        $timestamp = 1_780_000_020;
        $now = new DateTimeImmutable('@' . $timestamp);
        $totp = TOTP::create($secret->revealForTotp(), 30, 'sha1', 6);
        $previousCode = $totp->at($timestamp - 30);
        $verifier = new TotpVerifier($configuration);
        $counter = $verifier->acceptedCounter($secret, $previousCode, $now, null);

        self::assertSame(intdiv($now->getTimestamp(), 30) - 1, $counter);
        self::assertNull($verifier->acceptedCounter($secret, $previousCode, $now, $counter));
        self::assertNull($verifier->acceptedCounter($secret, '00000A', $now, null));
        self::assertStringStartsWith('otpauth://totp/', $verifier->provisioningUri($secret, 'account-id'));
    }

    public function testRecoveryCodesAreUniqueNormalizedAndHmacProtected(): void
    {
        $codes = (new SecureRecoveryCodeGenerator($this->configuration()))->generateSet();
        $revealed = array_map(static fn ($code): string => $code->revealOnce(), $codes);

        self::assertCount(10, $revealed);
        self::assertCount(10, array_unique($revealed));
        foreach ($revealed as $code) {
            self::assertMatchesRegularExpression(
                '/\A[0-9A-HJKMNP-TV-Z]{4}(?:-[0-9A-HJKMNP-TV-Z]{4}){5}-[0-9A-HJKMNP-TV-Z]{2}\z/',
                $code,
            );
        }
        $normalized = NormalizedRecoveryCode::fromInput(strtolower(str_replace('-', ' ', $revealed[0])));
        self::assertSame(
            $normalized->hash('test-hmac-key')->toBinary(),
            NormalizedRecoveryCode::fromInput($revealed[0])->hash('test-hmac-key')->toBinary(),
        );
        self::assertNotSame(
            $normalized->hash('test-hmac-key')->toBinary(),
            $normalized->hash('different-hmac-key')->toBinary(),
        );
    }

    private function configuration(): IdentityMultiFactorConfiguration
    {
        return new IdentityMultiFactorConfiguration(
            false,
            300,
            5,
            300,
            5,
            1,
            'Quran Memorizer DB',
            30,
            6,
            1,
            600,
            10,
            16,
            'localhost',
            'Quran Memorizer DB',
            ['http://localhost:8080'],
            300,
            65536,
            'required',
            'none',
            true,
            900,
            10,
            900,
            20,
        );
    }
}
