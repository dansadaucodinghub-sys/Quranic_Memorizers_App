<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityRecovery\Application;

use Qmdb\Modules\Identity\Domain\Value\EmailAddress;
use Qmdb\Modules\Identity\Infrastructure\Security\ContactCipher;
use Qmdb\Modules\IdentityAccess\Application\Mail\EmailDeliveryException;
use Qmdb\Modules\IdentityAccess\Domain\IdempotencyClaimStatus;
use Qmdb\Modules\IdentityAccess\Domain\Repository\IdentityAccessRepository;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\EmailLookupHashGenerator;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitAttempt;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitPolicy;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitScope;
use Qmdb\Modules\IdentityRecovery\Application\Mail\PasswordRecoveryMessageFactory;
use Qmdb\Modules\IdentityRecovery\Application\Mail\PasswordRecoveryNotifier;
use Qmdb\Modules\IdentityRecovery\Configuration\IdentityRecoveryConfiguration;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryChallengeId;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryEventType;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryTokenGenerator;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryTokenHash;
use Qmdb\Modules\IdentityRecovery\Domain\Repository\PasswordRecoveryChallengeRepository;
use Qmdb\Modules\IdentityRecovery\Domain\Repository\PasswordRecoveryEventRepository;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Time\Clock;
use Throwable;

final readonly class PasswordRecoveryRequestService
{
    public function __construct(
        private PasswordRecoveryChallengeRepository $challenges,
        private PasswordRecoveryEventRepository $events,
        private IdentityAccessRepository $idempotency,
        private TransactionManager $transactions,
        private PasswordRecoveryTokenGenerator $tokens,
        private IdentityRateLimiter $rateLimiter,
        private IdentityFingerprintGenerator $fingerprints,
        private EmailLookupHashGenerator $emailHashes,
        private ContactCipher $contacts,
        private PasswordRecoveryMessageFactory $messages,
        private PasswordRecoveryNotifier $notifier,
        private IdentityRecoveryConfiguration $configuration,
        private Clock $clock,
    ) {
    }

    public function request(PasswordRecoveryRequestCommand $command): PasswordRecoveryRequestResult
    {
        $normalizedEmail = EmailAddress::fromInput($command->email)->normalized();
        $decision = $this->rateLimiter->consume($this->rateAttempts($normalizedEmail, $command), $this->clock->now());
        if (!$decision->allowed) {
            return PasswordRecoveryRequestResult::throttled($decision->retryAfterSeconds);
        }
        $token = $this->tokens->generate();
        $tokenHash = PasswordRecoveryTokenHash::fromToken($token);
        $emailHash = $this->emailHashes->generate($normalizedEmail);
        $fingerprint = $this->fingerprints->generate('idempotency-recovery-request', $normalizedEmail);
        $now = $this->clock->now();
        $expiresAt = $now->modify('+' . $this->configuration->ttlSeconds . ' seconds');

        $delivery = $this->transactions->transactional(function () use (
            $command,
            $emailHash,
            $fingerprint,
            $token,
            $tokenHash,
            $now,
            $expiresAt,
        ): PasswordRecoveryDelivery|PasswordRecoveryRequestOutcome {
            $claim = $this->idempotency->claimIdempotency(
                $command->submissionId,
                'PASSWORD_RECOVERY_REQUEST',
                $fingerprint,
                $now,
            );
            if ($claim === IdempotencyClaimStatus::CONFLICT) {
                return PasswordRecoveryRequestOutcome::IDEMPOTENCY_CONFLICT;
            }
            if ($claim === IdempotencyClaimStatus::REPLAY) {
                return PasswordRecoveryRequestOutcome::ACCEPTED;
            }
            $target = $this->challenges->targetByEmailHash($emailHash);
            if ($target === null || !$target->eligible()) {
                $this->idempotency->completeIdempotency($command->submissionId, $now);
                return PasswordRecoveryRequestOutcome::ACCEPTED;
            }
            $this->challenges->revokePendingForAccount($target->accountInternalId, $now);
            $challengeId = PasswordRecoveryChallengeId::generate();
            $challengeInternalId = $this->challenges->createPending(
                $target,
                $challengeId,
                $tokenHash,
                $command->locale,
                $this->configuration->maximumAttempts,
                $expiresAt,
                $now,
            );
            $this->events->append(
                $challengeInternalId,
                $target->accountInternalId,
                PasswordRecoveryEventType::REQUESTED,
                $now,
                correlationId: $command->correlationId,
            );
            $this->idempotency->completeIdempotency($command->submissionId, $now);

            return new PasswordRecoveryDelivery(
                $challengeInternalId,
                $target->accountInternalId,
                $target->emailCiphertext,
                $command->locale,
                $challengeId,
                $token,
                $expiresAt,
            );
        });
        if ($delivery === PasswordRecoveryRequestOutcome::IDEMPOTENCY_CONFLICT) {
            return PasswordRecoveryRequestResult::conflict();
        }
        if (!$delivery instanceof PasswordRecoveryDelivery) {
            return PasswordRecoveryRequestResult::accepted();
        }
        try {
            $recipient = $this->contacts->decrypt($delivery->emailCiphertext);
            $this->notifier->send($this->messages->create(
                $recipient,
                $delivery->locale,
                $delivery->challengeId,
                $delivery->token,
                $delivery->expiresAt,
            ));
        } catch (Throwable $failure) {
            $failureCode = $failure instanceof EmailDeliveryException
                ? 'MAIL_TRANSPORT'
                : 'DELIVERY_CONFIGURATION';
            $this->transactions->transactional(function () use ($delivery, $command, $failureCode): void {
                $this->events->append(
                    $delivery->challengeInternalId,
                    $delivery->accountInternalId,
                    PasswordRecoveryEventType::DELIVERY_FAILED,
                    $this->clock->now(),
                    failureCode: $failureCode,
                    correlationId: $command->correlationId,
                );
            });
        }

        return PasswordRecoveryRequestResult::accepted();
    }

    /** @return non-empty-list<IdentityRateLimitAttempt> */
    private function rateAttempts(string $email, PasswordRecoveryRequestCommand $command): array
    {
        $policy = new IdentityRateLimitPolicy(
            $this->configuration->requestWindowSeconds,
            $this->configuration->requestMaximumAttempts,
            $this->configuration->requestWindowSeconds,
        );

        return [
            new IdentityRateLimitAttempt(
                IdentityRateLimitScope::PASSWORD_RECOVERY_REQUEST_EMAIL,
                $this->fingerprints->generate('recovery-request-email', $email),
                $policy,
            ),
            new IdentityRateLimitAttempt(
                IdentityRateLimitScope::PASSWORD_RECOVERY_REQUEST_PEER,
                $this->fingerprints->generate('recovery-request-peer', $command->peer->fingerprintInput()),
                $policy,
            ),
        ];
    }
}
