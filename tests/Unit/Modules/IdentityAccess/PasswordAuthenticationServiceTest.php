<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Modules\IdentityAccess;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Qmdb\Modules\Identity\Domain\AccountContactStatus;
use Qmdb\Modules\Identity\Domain\AccountStatus;
use Qmdb\Modules\Identity\Domain\CredentialStatus;
use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\Identity\Domain\Value\SensitivePasswordHash;
use Qmdb\Modules\IdentityAccess\Application\Authentication\PasswordAuthenticationOutcome;
use Qmdb\Modules\IdentityAccess\Application\Authentication\PasswordAuthenticationRecord;
use Qmdb\Modules\IdentityAccess\Application\Authentication\PasswordAuthenticationRepository;
use Qmdb\Modules\IdentityAccess\Application\Authentication\PasswordAuthenticationService;
use Qmdb\Modules\IdentityAccess\Configuration\IdentityAccessConfiguration;
use Qmdb\Modules\IdentityAccess\Configuration\PublicApplicationBaseUrl;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\EmailLookupHashGenerator;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\HmacIdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\Password\DummyPasswordHashProvider;
use Qmdb\Modules\IdentityAccess\Security\Password\PasswordHashingPolicy;
use Qmdb\Modules\IdentityAccess\Security\Password\PasswordVerificationResult;
use Qmdb\Modules\IdentityAccess\Security\Password\PasswordVerifier;
use Qmdb\Modules\IdentityAccess\Security\Password\SensitivePlaintextPassword;
use Qmdb\Modules\IdentityAccess\Security\Peer\DirectPeerAddress;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitDecision;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Tests\Support\IdentityAccess\FixedIdentityClock;
use Qmdb\Tests\Support\IdentityAccess\RecordingAuthenticationRepository;
use Qmdb\Tests\Support\IdentityAccess\RecordingIdentityRateLimiter;
use Qmdb\Tests\Support\IdentityAccess\RecordingPasswordVerifier;

final class PasswordAuthenticationServiceTest extends TestCase
{
    public function testUnknownAccountUsesDummyHashAndReturnsGenericFailure(): void
    {
        $repository = new RecordingAuthenticationRepository(null);
        $verifier = new RecordingPasswordVerifier(new PasswordVerificationResult(false, false));
        $rateLimiter = new RecordingIdentityRateLimiter(IdentityRateLimitDecision::allowed());
        $result = $this->service($repository, $verifier, $rateLimiter)->authenticate(
            'unknown@example.test',
            new SensitivePlaintextPassword('Unknown passphrase 123!'),
            DirectPeerAddress::fromObserved('192.0.2.10'),
        );

        self::assertSame(PasswordAuthenticationOutcome::INVALID_CREDENTIALS, $result->outcome);
        self::assertSame(1, $verifier->calls);
        self::assertNotNull($verifier->lastHash);
        self::assertStringStartsWith('$argon2id$', $verifier->lastHash->revealForVerification());
        self::assertSame(1, $rateLimiter->consumeCalls);
        self::assertSame(0, $rateLimiter->resetCalls);
    }

    public function testVerifiedActiveAccountReturnsPrincipalAndResetsThrottle(): void
    {
        $accountId = AccountId::generate();
        $repository = new RecordingAuthenticationRepository(new PasswordAuthenticationRecord(
            42,
            $accountId,
            AccountStatus::ACTIVE,
            AccountContactStatus::VERIFIED,
            CredentialStatus::ACTIVE,
            new SensitivePasswordHash(password_hash('Valid passphrase 123!', PASSWORD_ARGON2ID)),
            'argon2id',
            1,
        ));
        $verifier = new RecordingPasswordVerifier(new PasswordVerificationResult(true, true));
        $rateLimiter = new RecordingIdentityRateLimiter(IdentityRateLimitDecision::allowed());
        $result = $this->service($repository, $verifier, $rateLimiter)->authenticate(
            'person@example.test',
            new SensitivePlaintextPassword('Valid passphrase 123!'),
            DirectPeerAddress::fromObserved('192.0.2.10'),
        );

        self::assertSame(PasswordAuthenticationOutcome::VERIFIED, $result->outcome);
        self::assertSame(42, $result->verifiedPrincipal()->accountInternalId);
        self::assertSame($accountId->toString(), $result->verifiedPrincipal()->accountId->toString());
        self::assertTrue($result->passwordRehashRequired);
        self::assertSame(1, $rateLimiter->resetCalls);
    }

    public function testThrottleFailsBeforeRepositoryOrPasswordVerification(): void
    {
        $repository = new RecordingAuthenticationRepository(null);
        $verifier = new RecordingPasswordVerifier(new PasswordVerificationResult(false, false));
        $rateLimiter = new RecordingIdentityRateLimiter(IdentityRateLimitDecision::throttled(45));
        $result = $this->service($repository, $verifier, $rateLimiter)->authenticate(
            'person@example.test',
            new SensitivePlaintextPassword('Valid passphrase 123!'),
            DirectPeerAddress::fromObserved('192.0.2.10'),
        );

        self::assertSame(PasswordAuthenticationOutcome::THROTTLED, $result->outcome);
        self::assertSame(45, $result->retryAfterSeconds);
        self::assertSame(0, $repository->calls);
        self::assertSame(0, $verifier->calls);
    }

    private function service(
        PasswordAuthenticationRepository $repository,
        PasswordVerifier $verifier,
        IdentityRateLimiter $rateLimiter,
    ): PasswordAuthenticationService {
        return new PasswordAuthenticationService(
            $repository,
            $verifier,
            new DummyPasswordHashProvider(new PasswordHashingPolicy([
                'memory_cost' => 8192,
                'time_cost' => 1,
                'threads' => 1,
            ])),
            $rateLimiter,
            new HmacIdentityFingerprintGenerator(str_repeat('f', 32)),
            new EmailLookupHashGenerator(str_repeat('e', 32)),
            self::configuration(),
            new FixedIdentityClock(new DateTimeImmutable('2026-08-26T12:00:00Z')),
        );
    }

    private static function configuration(): IdentityAccessConfiguration
    {
        return new IdentityAccessConfiguration(
            new PublicApplicationBaseUrl('http://localhost', false),
            false,
            1800,
            16384,
            12,
            1024,
            1800,
            5,
            900,
            5,
            900,
            3,
            900,
            10,
            900,
            'test-v1',
            'no-reply@example.test',
            'QMDB Test',
        );
    }
}
