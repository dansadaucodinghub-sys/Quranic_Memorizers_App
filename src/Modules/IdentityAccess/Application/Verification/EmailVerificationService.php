<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Application\Verification;

use Qmdb\Modules\IdentityAccess\Configuration\IdentityAccessConfiguration;
use Qmdb\Modules\IdentityAccess\Domain\EmailVerificationChallengeStatus;
use Qmdb\Modules\IdentityAccess\Domain\Repository\IdentityAccessRepository;
use Qmdb\Modules\IdentityAccess\Domain\VerificationPersistenceResult;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitAttempt;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitPolicy;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitScope;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Time\Clock;

final readonly class EmailVerificationService
{
    public function __construct(
        private IdentityAccessRepository $access,
        private TransactionManager $transactions,
        private IdentityRateLimiter $rateLimiter,
        private IdentityFingerprintGenerator $fingerprints,
        private IdentityAccessConfiguration $configuration,
        private Clock $clock,
    ) {
    }

    public function verify(EmailVerificationCommand $command): EmailVerificationResult
    {
        $policy = new IdentityRateLimitPolicy(
            $this->configuration->registrationWindowSeconds,
            $this->configuration->verificationMaximumAttempts,
            $this->configuration->rateLimitBlockSeconds,
        );
        $decision = $this->rateLimiter->consume([
            new IdentityRateLimitAttempt(
                IdentityRateLimitScope::EMAIL_VERIFICATION_ATTEMPT,
                $this->fingerprints->generate('verification-challenge', $command->challengeId->toString()),
                $policy,
            ),
            new IdentityRateLimitAttempt(
                IdentityRateLimitScope::EMAIL_VERIFICATION_PEER,
                $this->fingerprints->generate('verification-peer', $command->peer->fingerprintInput()),
                $policy,
            ),
        ], $this->clock->now());
        if (!$decision->allowed) {
            return EmailVerificationResult::throttled($decision->retryAfterSeconds);
        }
        $now = $this->clock->now();

        return $this->transactions->transactional(function () use ($command, $now): EmailVerificationResult {
            $challenge = $this->access->lockChallenge($command->challengeId);
            if ($challenge === null) {
                return EmailVerificationResult::invalid();
            }
            if (!$challenge->tokenHash->matches($command->token)) {
                if ($challenge->status === EmailVerificationChallengeStatus::PENDING) {
                    $this->access->failChallenge($challenge, $now);
                }
                return EmailVerificationResult::invalid();
            }
            if ($challenge->status === EmailVerificationChallengeStatus::PENDING && $challenge->expiresAt <= $now) {
                $this->access->expireChallenge($challenge, $now);
                return EmailVerificationResult::invalid();
            }
            $result = $this->access->consumeChallenge($challenge, $now);

            return in_array(
                $result,
                [VerificationPersistenceResult::COMPLETED, VerificationPersistenceResult::ALREADY_COMPLETED],
                true,
            ) ? EmailVerificationResult::completed() : EmailVerificationResult::invalid();
        });
    }
}
