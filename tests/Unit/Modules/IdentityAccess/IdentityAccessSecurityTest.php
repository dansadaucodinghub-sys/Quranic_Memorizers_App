<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Modules\IdentityAccess;

use DateTimeImmutable;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Qmdb\Modules\IdentityAccess\Configuration\PublicApplicationBaseUrl;
use Qmdb\Modules\IdentityAccess\Domain\EmailVerificationTokenHash;
use Qmdb\Modules\IdentityAccess\Infrastructure\Security\SecureEmailVerificationTokenGenerator;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\HmacIdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\Password\Argon2IdPasswordHasher;
use Qmdb\Modules\IdentityAccess\Security\Password\Argon2IdPasswordVerifier;
use Qmdb\Modules\IdentityAccess\Security\Password\DummyPasswordHashProvider;
use Qmdb\Modules\IdentityAccess\Security\Password\PasswordHashingPolicy;
use Qmdb\Modules\IdentityAccess\Security\Password\PasswordPolicy;
use Qmdb\Modules\IdentityAccess\Security\Password\SensitivePlaintextPassword;
use Qmdb\Modules\IdentityAccess\Security\Peer\DirectPeerAddressResolver;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfCookieNonce;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfTokenVerificationResult;
use Qmdb\Modules\SecurityWeb\Csrf\HmacCsrfTokenManager;

final class IdentityAccessSecurityTest extends TestCase
{
    public function testPasswordPolicyPreservesExactSecretAndArgon2IdRoundTrips(): void
    {
        $policy = new PasswordPolicy(12, 1024);
        $hashing = new PasswordHashingPolicy(['memory_cost' => 8192, 'time_cost' => 1, 'threads' => 1]);
        $password = new SensitivePlaintextPassword(' exact passphrase 123 ');
        self::assertTrue($policy->evaluate($password, ' exact passphrase 123 ')->accepted());

        $hash = (new Argon2IdPasswordHasher($policy, $hashing))->hash($password, ' exact passphrase 123 ');
        self::assertStringStartsWith('$argon2id$', $hash->hash->revealForVerification());
        self::assertTrue((new Argon2IdPasswordVerifier($hashing))->verify($password, $hash->hash)->verified);
        self::assertFalse((new Argon2IdPasswordVerifier($hashing))->verify(
            new SensitivePlaintextPassword('exact passphrase 123'),
            $hash->hash,
        )->verified);
    }

    public function testPasswordPolicyRejectsShortNulWhitespaceAndMismatchedValues(): void
    {
        $policy = new PasswordPolicy();
        foreach (
            [
                ['short', 'short'],
                ["valid-length\0password", "valid-length\0password"],
                ['                ', '                '],
                ['valid passphrase 123', 'different passphrase'],
            ] as [$password, $confirmation]
        ) {
            self::assertFalse($policy->evaluate(
                new SensitivePlaintextPassword($password),
                $confirmation,
            )->accepted());
        }
    }

    public function testDummyHashIsArgon2IdAndNeverContainsSourceSecret(): void
    {
        $hashing = new PasswordHashingPolicy(['memory_cost' => 8192, 'time_cost' => 1, 'threads' => 1]);
        $dummy = (new DummyPasswordHashProvider($hashing))->hash()->revealForVerification();
        self::assertStringStartsWith('$argon2id$', $dummy);
        self::assertFalse(password_verify('known password value', $dummy));
    }

    public function testCsrfTokenIsActionCookieAndTimeBound(): void
    {
        $manager = new HmacCsrfTokenManager(str_repeat('k', 32), 60);
        $nonce = CsrfCookieNonce::generate();
        $now = new DateTimeImmutable('2026-08-26T12:00:00Z');
        $token = $manager->issue(CsrfAction::ACCOUNT_REGISTER, $nonce, $now);
        self::assertSame(
            CsrfTokenVerificationResult::VALID,
            $manager->verify(CsrfAction::ACCOUNT_REGISTER, $nonce, $token, $now->modify('+60 seconds')),
        );
        self::assertSame(
            CsrfTokenVerificationResult::INVALID,
            $manager->verify(CsrfAction::ACCOUNT_EMAIL_VERIFY, $nonce, $token, $now),
        );
        self::assertSame(
            CsrfTokenVerificationResult::INVALID,
            $manager->verify(CsrfAction::ACCOUNT_REGISTER, CsrfCookieNonce::generate(), $token, $now),
        );
        self::assertSame(
            CsrfTokenVerificationResult::INVALID,
            $manager->verify(CsrfAction::ACCOUNT_REGISTER, $nonce, $token, $now->modify('+61 seconds')),
        );
    }

    public function testFingerprintsAreDomainSeparatedAndDoNotExposeInputs(): void
    {
        $generator = new HmacIdentityFingerprintGenerator(str_repeat('h', 32));
        $email = $generator->generate('registration-email', 'person@example.test');
        $peer = $generator->generate('registration-peer', 'person@example.test');
        self::assertNotSame($email->toBinary(), $peer->toBinary());
        self::assertSame(32, strlen($email->toBinary()));
        self::assertStringNotContainsString('person@example.test', $email->toBinary());
    }

    public function testPeerResolverUsesOnlyDirectRemoteAddress(): void
    {
        $request = (new ServerRequest('POST', '/register', ['X-Forwarded-For' => '203.0.113.10'], null, '1.1', [
            'REMOTE_ADDR' => '192.0.2.44',
        ]));
        self::assertSame('192.0.2.44', (new DirectPeerAddressResolver())->resolve($request)->fingerprintInput());
    }

    public function testVerificationTokensUseSecureShapeAndHashOnlyComparison(): void
    {
        $generator = new SecureEmailVerificationTokenGenerator();
        $token = $generator->generate();
        $hash = EmailVerificationTokenHash::fromToken($token);
        self::assertTrue($hash->matches($token));
        self::assertSame(32, strlen($hash->toBinary()));
    }

    public function testPublicApplicationBaseUrlIsCanonicalAndProductionRequiresHttps(): void
    {
        $url = new PublicApplicationBaseUrl('https://EXAMPLE.test/', true);
        self::assertSame('https://example.test/verify', $url->path('/verify'));
        $this->expectException(\InvalidArgumentException::class);
        new PublicApplicationBaseUrl('http://example.test', true);
    }
}
