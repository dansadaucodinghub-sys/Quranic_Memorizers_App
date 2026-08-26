<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Application\Authentication;

use Qmdb\Modules\Identity\Domain\AccountContactStatus;
use Qmdb\Modules\Identity\Domain\AccountStatus;
use Qmdb\Modules\Identity\Domain\CredentialStatus;
use Qmdb\Modules\Identity\Domain\Value\EmailAddress;
use Qmdb\Modules\IdentityAccess\Configuration\IdentityAccessConfiguration;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\EmailLookupHashGenerator;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\Password\DummyPasswordHashProvider;
use Qmdb\Modules\IdentityAccess\Security\Password\PasswordVerifier;
use Qmdb\Modules\IdentityAccess\Security\Password\SensitivePlaintextPassword;
use Qmdb\Modules\IdentityAccess\Security\Peer\DirectPeerAddress;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitAttempt;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitPolicy;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitScope;
use Qmdb\Shared\Time\Clock;

final readonly class PasswordAuthenticationService
{
    public function __construct(
        private PasswordAuthenticationRepository $repository,
        private PasswordVerifier $verifier,
        private DummyPasswordHashProvider $dummyHash,
        private IdentityRateLimiter $rateLimiter,
        private IdentityFingerprintGenerator $fingerprints,
        private EmailLookupHashGenerator $emailHashes,
        private IdentityAccessConfiguration $configuration,
        private Clock $clock,
    ) {
    }

    public function authenticate(
        string $emailInput,
        SensitivePlaintextPassword $password,
        DirectPeerAddress $peer,
    ): PasswordAuthenticationResult {
        try {
            $email = EmailAddress::fromInput($emailInput)->normalized();
        } catch (\InvalidArgumentException) {
            $email = strtolower(trim($emailInput));
            $validEmail = false;
        }
        $validEmail ??= true;
        $policy = new IdentityRateLimitPolicy(
            $this->configuration->passwordWindowSeconds,
            $this->configuration->passwordMaximumAttempts,
            $this->configuration->rateLimitBlockSeconds,
        );
        $attempts = [
            new IdentityRateLimitAttempt(
                IdentityRateLimitScope::PASSWORD_AUTHENTICATION_EMAIL,
                $this->fingerprints->generate('password-email', $email),
                $policy,
            ),
            new IdentityRateLimitAttempt(
                IdentityRateLimitScope::PASSWORD_AUTHENTICATION_PEER,
                $this->fingerprints->generate('password-peer', $peer->fingerprintInput()),
                $policy,
            ),
        ];
        $rate = $this->rateLimiter->consume($attempts, $this->clock->now());
        if (!$rate->allowed) {
            return PasswordAuthenticationResult::throttled($rate->retryAfterSeconds);
        }
        $record = $validEmail ? $this->repository->byEmailHash($this->emailHashes->generate($email)) : null;
        $hash = $record === null ? $this->dummyHash->hash() : $record->passwordHash;
        $verification = $this->verifier->verify($password, $hash);
        if (
            !$verification->verified
            || $record === null
            || $record->accountStatus !== AccountStatus::ACTIVE
            || $record->emailStatus !== AccountContactStatus::VERIFIED
            || $record->credentialStatus !== CredentialStatus::ACTIVE
        ) {
            return PasswordAuthenticationResult::invalid();
        }
        $this->rateLimiter->reset($attempts);

        return PasswordAuthenticationResult::verified(
            new VerifiedAccountPrincipal($record->accountInternalId, $record->accountId),
            $verification->rehashRequired,
        );
    }
}
