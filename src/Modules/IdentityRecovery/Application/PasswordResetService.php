<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityRecovery\Application;

use Qmdb\Modules\Identity\Domain\AccountContactStatus;
use Qmdb\Modules\Identity\Domain\AccountStatus;
use Qmdb\Modules\IdentityAccess\Domain\IdempotencyClaimStatus;
use Qmdb\Modules\IdentityAccess\Domain\Repository\IdentityAccessRepository;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\Password\PasswordHasher;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitAttempt;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitPolicy;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitScope;
use Qmdb\Modules\IdentityRecovery\Configuration\IdentityRecoveryConfiguration;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryChallenge;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryChallengeStatus;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryEventType;
use Qmdb\Modules\IdentityRecovery\Domain\Repository\PasswordRecoveryChallengeRepository;
use Qmdb\Modules\IdentityRecovery\Domain\Repository\PasswordRecoveryEventRepository;
use Qmdb\Modules\IdentitySecurityNotifications\Configuration\SecurityNotificationConfiguration;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationId;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\Repository\AccountSecurityNotificationRepository;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\SecurityNotificationDeduplicationKeyFactory;
use Qmdb\Modules\IdentitySessions\Domain\Repository\UserSessionRepository;
use Qmdb\Modules\IdentitySessions\Domain\SessionRevocationReason;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Time\Clock;

final readonly class PasswordResetService
{
    public function __construct(
        private PasswordRecoveryChallengeRepository $challenges,
        private PasswordRecoveryEventRepository $events,
        private AccountSecurityNotificationRepository $notifications,
        private IdentityAccessRepository $idempotency,
        private UserSessionRepository $sessions,
        private TransactionManager $transactions,
        private PasswordHasher $passwords,
        private IdentityRateLimiter $rateLimiter,
        private IdentityFingerprintGenerator $fingerprints,
        private SecurityNotificationDeduplicationKeyFactory $deduplicationKeys,
        private IdentityRecoveryConfiguration $configuration,
        private SecurityNotificationConfiguration $notificationConfiguration,
        private SecurityAuditEventAppender $audit,
        private Clock $clock,
    ) {
    }

    public function reset(PasswordResetCommand $command): PasswordResetResult
    {
        $now = $this->clock->now();
        $rateDecision = $this->rateLimiter->consume($this->rateAttempts($command), $now);
        if (!$rateDecision->allowed) {
            return PasswordResetResult::throttled($rateDecision->retryAfterSeconds);
        }
        $candidate = $this->challenges->findByPublicId($command->challengeId);
        if ($candidate === null || !$candidate->accepts($command->token, $now)) {
            $this->recordRejectedCandidate($candidate, $command, $now);
            return PasswordResetResult::invalid();
        }
        $passwordHash = $this->passwords->hash($command->password, $command->passwordConfirmation);
        $fingerprint = $this->fingerprints->generate(
            'idempotency-recovery-reset',
            $command->challengeId->toString() . "\0" . $candidate->tokenHash->toBinary(),
        );

        return $this->transactions->transactional(function () use (
            $candidate,
            $command,
            $fingerprint,
            $passwordHash,
            $now,
        ): PasswordResetResult {
            $this->sessions->lockAccount($candidate->accountInternalId);
            $locked = $this->challenges->lockByPublicId($command->challengeId);
            if ($locked === null || !$this->authorizesReset($locked, $command, $now)) {
                return PasswordResetResult::invalid();
            }
            $claim = $this->idempotency->claimIdempotency(
                $command->submissionId,
                'PASSWORD_RECOVERY_RESET',
                $fingerprint,
                $now,
            );
            if ($claim === IdempotencyClaimStatus::CONFLICT) {
                return PasswordResetResult::conflict();
            }
            if ($claim === IdempotencyClaimStatus::REPLAY) {
                return PasswordResetResult::completed();
            }
            $this->challenges->replacePasswordCredential($locked->accountInternalId, $passwordHash, $now);
            if (!$this->challenges->markConsumed($locked, $now)) {
                throw new \UnexpectedValueException('Password recovery challenge consumption failed.');
            }
            $this->challenges->revokePendingForAccount($locked->accountInternalId, $now);
            $this->sessions->revokeAllForAccount(
                $locked->accountInternalId,
                SessionRevocationReason::PASSWORD_RESET,
                $now,
            );
            $this->audit->account(
                SecurityEventCode::PASSWORD_RESET_COMPLETED,
                $locked->accountId->toString(),
                null,
                null,
                $now,
            );
            $this->notifications->createPendingIntent(
                AccountSecurityNotificationId::generate(),
                $locked->accountInternalId,
                $locked->emailInternalId,
                AccountSecurityNotificationType::PASSWORD_RESET_COMPLETED,
                $this->deduplicationKeys->passwordResetCompleted(
                    $locked->id->toString(),
                    $locked->accountId->toString(),
                ),
                $locked->locale,
                $this->notificationConfiguration->maximumAttempts,
                $now,
            );
            $this->events->append(
                $locked->internalId,
                $locked->accountInternalId,
                PasswordRecoveryEventType::COMPLETED,
                $now,
                correlationId: $command->correlationId,
            );
            $this->idempotency->completeIdempotency($command->submissionId, $now);

            return PasswordResetResult::completed();
        });
    }

    private function recordRejectedCandidate(
        ?PasswordRecoveryChallenge $candidate,
        PasswordResetCommand $command,
        \DateTimeImmutable $now,
    ): void {
        if ($candidate === null || $candidate->status !== PasswordRecoveryChallengeStatus::PENDING) {
            return;
        }
        $this->transactions->transactional(function () use ($candidate, $command, $now): void {
            $locked = $this->challenges->lockByPublicId($candidate->id);
            if ($locked === null || $locked->status !== PasswordRecoveryChallengeStatus::PENDING) {
                return;
            }
            if ($now >= $locked->expiresAt) {
                $this->challenges->markExpired($locked, $now);
                $this->events->append(
                    $locked->internalId,
                    $locked->accountInternalId,
                    PasswordRecoveryEventType::EXPIRED,
                    $now,
                    correlationId: $command->correlationId,
                );
                return;
            }
            $this->challenges->incrementInvalidAttempt($locked, $now);
            $this->events->append(
                $locked->internalId,
                $locked->accountInternalId,
                PasswordRecoveryEventType::TOKEN_REJECTED,
                $now,
                $locked->attemptCount + 1,
                correlationId: $command->correlationId,
            );
        });
    }

    private function authorizesReset(
        PasswordRecoveryChallenge $challenge,
        PasswordResetCommand $command,
        \DateTimeImmutable $now,
    ): bool {
        return $challenge->accepts($command->token, $now)
            && $challenge->accountStatus === AccountStatus::ACTIVE
            && $challenge->emailStatus === AccountContactStatus::VERIFIED;
    }

    /** @return non-empty-list<IdentityRateLimitAttempt> */
    private function rateAttempts(PasswordResetCommand $command): array
    {
        $policy = new IdentityRateLimitPolicy(
            $this->configuration->confirmWindowSeconds,
            $this->configuration->confirmMaximumAttempts,
            $this->configuration->confirmWindowSeconds,
        );

        return [
            new IdentityRateLimitAttempt(
                IdentityRateLimitScope::PASSWORD_RECOVERY_ATTEMPT,
                $this->fingerprints->generate('recovery-attempt', $command->challengeId->toString()),
                $policy,
            ),
            new IdentityRateLimitAttempt(
                IdentityRateLimitScope::PASSWORD_RECOVERY_ATTEMPT_PEER,
                $this->fingerprints->generate('recovery-attempt-peer', $command->peer->fingerprintInput()),
                $policy,
            ),
        ];
    }
}
