<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Application\Verification;

use Qmdb\Modules\Identity\Domain\Value\EmailAddress;
use Qmdb\Modules\IdentityAccess\Application\Mail\EmailDeliveryException;
use Qmdb\Modules\IdentityAccess\Application\Mail\EmailVerificationMessageFactory;
use Qmdb\Modules\IdentityAccess\Application\Mail\EmailVerificationNotifier;
use Qmdb\Modules\IdentityAccess\Configuration\IdentityAccessConfiguration;
use Qmdb\Modules\IdentityAccess\Domain\EmailVerificationChallengeId;
use Qmdb\Modules\IdentityAccess\Domain\EmailVerificationTokenGenerator;
use Qmdb\Modules\IdentityAccess\Domain\EmailVerificationTokenHash;
use Qmdb\Modules\IdentityAccess\Domain\IdempotencyClaimStatus;
use Qmdb\Modules\IdentityAccess\Domain\Repository\IdentityAccessRepository;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\EmailLookupHashGenerator;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitAttempt;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitPolicy;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitScope;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Configuration\Logging\LogLevel;
use Qmdb\Shared\Observability\Logging\EventLogger;
use Qmdb\Shared\Observability\Logging\LogEventName;
use Qmdb\Shared\Time\Clock;

final readonly class EmailVerificationResendService
{
    public function __construct(
        private IdentityAccessRepository $access,
        private TransactionManager $transactions,
        private EmailVerificationTokenGenerator $tokens,
        private IdentityRateLimiter $rateLimiter,
        private IdentityFingerprintGenerator $fingerprints,
        private EmailLookupHashGenerator $emailHashes,
        private EmailVerificationMessageFactory $messages,
        private EmailVerificationNotifier $notifier,
        private EventLogger $logger,
        private IdentityAccessConfiguration $configuration,
        private Clock $clock,
    ) {
    }

    public function resend(EmailVerificationResendCommand $command): EmailVerificationResendResult
    {
        $email = EmailAddress::fromInput($command->email)->normalized();
        $policy = new IdentityRateLimitPolicy(
            $this->configuration->resendWindowSeconds,
            $this->configuration->resendMaximumAttempts,
            $this->configuration->rateLimitBlockSeconds,
        );
        $rateDecision = $this->rateLimiter->consume([
            new IdentityRateLimitAttempt(
                IdentityRateLimitScope::EMAIL_VERIFICATION_RESEND_EMAIL,
                $this->fingerprints->generate('verification-email', $email),
                $policy,
            ),
            new IdentityRateLimitAttempt(
                IdentityRateLimitScope::EMAIL_VERIFICATION_RESEND_PEER,
                $this->fingerprints->generate('verification-peer', $command->peer->fingerprintInput()),
                $policy,
            ),
        ], $this->clock->now());
        if (!$rateDecision->allowed) {
            return EmailVerificationResendResult::throttled($rateDecision->retryAfterSeconds);
        }
        $token = $this->tokens->generate();
        $tokenHash = EmailVerificationTokenHash::fromToken($token);
        $lookupHash = $this->emailHashes->generate($email);
        $requestFingerprint = $this->fingerprints->generate('idempotency-verification-resend', $email);
        $now = $this->clock->now();
        $expiresAt = $now->modify('+' . $this->configuration->verificationTtlSeconds . ' seconds');
        $result = $this->transactions->transactional(function () use (
            $command,
            $requestFingerprint,
            $now,
            $lookupHash,
            $tokenHash,
            $token,
            $expiresAt,
        ): ResendTransactionResult {
            $claim = $this->access->claimIdempotency(
                $command->submissionId,
                'EMAIL_VERIFICATION_RESEND',
                $requestFingerprint,
                $now,
            );
            if ($claim === IdempotencyClaimStatus::CONFLICT) {
                return ResendTransactionResult::conflict();
            }
            if ($claim === IdempotencyClaimStatus::REPLAY) {
                return ResendTransactionResult::accepted();
            }
            $target = $this->access->pendingVerificationTarget($lookupHash);
            if ($target === null) {
                $this->access->completeIdempotency($command->submissionId, $now);
                return ResendTransactionResult::accepted();
            }
            $this->access->revokePendingChallenges($target->emailInternalId, $now);
            $challengeId = EmailVerificationChallengeId::generate();
            $this->access->createChallenge(
                $target->emailInternalId,
                $challengeId,
                $tokenHash,
                $this->configuration->verificationMaximumAttempts,
                $expiresAt,
                $now,
            );
            $this->access->completeIdempotency($command->submissionId, $now);

            return ResendTransactionResult::delivery($challengeId, $token, $expiresAt);
        });
        if ($result->conflict) {
            return EmailVerificationResendResult::conflict();
        }
        if ($result->challengeId !== null && $result->token !== null && $result->expiresAt !== null) {
            try {
                $this->notifier->send($this->messages->create(
                    $email,
                    $command->locale,
                    $result->challengeId,
                    $result->token,
                    $result->expiresAt,
                ));
            } catch (EmailDeliveryException) {
                $this->logger->log(
                    LogLevel::WARNING,
                    new LogEventName('identity.email.verification.delivery.failed'),
                    ['operation' => 'resend'],
                );
            }
        }

        return EmailVerificationResendResult::accepted();
    }
}
