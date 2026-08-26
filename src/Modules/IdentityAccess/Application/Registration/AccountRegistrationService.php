<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Application\Registration;

use DateTimeImmutable;
use Qmdb\Modules\Identity\Domain\AccountContactStatus;
use Qmdb\Modules\Identity\Domain\AccountStatus;
use Qmdb\Modules\Identity\Domain\Exception\DuplicateIdentityContactException;
use Qmdb\Modules\Identity\Domain\Repository\AccountRepository;
use Qmdb\Modules\Identity\Domain\UserAccount;
use Qmdb\Modules\Identity\Domain\Value\AccountEmailId;
use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\Identity\Domain\Value\CredentialId;
use Qmdb\Modules\Identity\Domain\Value\EmailAddress;
use Qmdb\Modules\Identity\Infrastructure\Security\ContactCipher;
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
use Qmdb\Modules\IdentityAccess\Security\Password\PasswordHasher;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitAttempt;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitPolicy;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitScope;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Configuration\Logging\LogLevel;
use Qmdb\Shared\Observability\Logging\EventLogger;
use Qmdb\Shared\Observability\Logging\LogEventName;
use Qmdb\Shared\Time\Clock;

final readonly class AccountRegistrationService
{
    public function __construct(
        private AccountRepository $accounts,
        private IdentityAccessRepository $access,
        private TransactionManager $transactions,
        private PasswordHasher $passwords,
        private EmailVerificationTokenGenerator $tokens,
        private IdentityRateLimiter $rateLimiter,
        private IdentityFingerprintGenerator $fingerprints,
        private EmailLookupHashGenerator $emailHashes,
        private ContactCipher $contactCipher,
        private EmailVerificationMessageFactory $messages,
        private EmailVerificationNotifier $notifier,
        private EventLogger $logger,
        private IdentityAccessConfiguration $configuration,
        private Clock $clock,
    ) {
    }

    public function register(AccountRegistrationCommand $command): AccountRegistrationResult
    {
        $email = EmailAddress::fromInput($command->email);
        $normalizedEmail = $email->normalized();
        $rateAttempts = $this->registrationRateAttempts($normalizedEmail, $command);
        $rateDecision = $this->rateLimiter->consume($rateAttempts, $this->clock->now());
        if (!$rateDecision->allowed) {
            return AccountRegistrationResult::throttled($rateDecision->retryAfterSeconds);
        }
        $passwordHash = $this->passwords->hash($command->password, $command->passwordConfirmation);
        $verificationToken = $this->tokens->generate();
        $verificationHash = EmailVerificationTokenHash::fromToken($verificationToken);
        $emailHash = $this->emailHashes->generate($normalizedEmail);
        $idempotency = $this->fingerprints->generate(
            'idempotency-registration',
            $normalizedEmail . "\0" . $command->password->revealForHashing(),
        );
        $now = $this->clock->now();
        $expiresAt = $now->modify('+' . $this->configuration->verificationTtlSeconds . ' seconds');

        try {
            $transactionResult = $this->transactions->transactional(function () use (
                $command,
                $emailHash,
                $idempotency,
                $now,
                $passwordHash,
                $verificationHash,
                $verificationToken,
                $expiresAt,
                $normalizedEmail,
            ): RegistrationTransactionResult {
                $claim = $this->access->claimIdempotency(
                    $command->submissionId,
                    'ACCOUNT_REGISTRATION',
                    $idempotency,
                    $now,
                );
                if ($claim === IdempotencyClaimStatus::CONFLICT) {
                    return RegistrationTransactionResult::conflict();
                }
                if ($claim === IdempotencyClaimStatus::REPLAY || $this->access->historicalEmailExists($emailHash)) {
                    $this->access->completeIdempotency($command->submissionId, $now);
                    return RegistrationTransactionResult::acceptedWithoutDelivery();
                }
                $accountInternalId = $this->accounts->create(new UserAccount(
                    null,
                    AccountId::generate(),
                    AccountStatus::PENDING_VERIFICATION,
                    $command->locale === 'ar' ? 'ar' : 'en',
                    'UTC',
                    1,
                    $now,
                    $now,
                ));
                $this->accounts->appendStatusEvent(
                    $accountInternalId,
                    'ACCOUNT_REGISTERED_PENDING_VERIFICATION',
                    $now,
                );
                $emailInternalId = $this->accounts->addEmail(
                    $accountInternalId,
                    AccountEmailId::generate(),
                    $this->contactCipher->encrypt($normalizedEmail),
                    $this->contactCipher->keyId(),
                    $emailHash,
                    AccountContactStatus::UNVERIFIED,
                    $now,
                );
                $this->accounts->addPasswordCredential(
                    $accountInternalId,
                    CredentialId::generate(),
                    $passwordHash->hash,
                    $passwordHash->algorithm,
                    $passwordHash->metadataVersion,
                    $now,
                );
                $challengeId = EmailVerificationChallengeId::generate();
                $this->access->createChallenge(
                    $emailInternalId,
                    $challengeId,
                    $verificationHash,
                    $this->configuration->verificationMaximumAttempts,
                    $expiresAt,
                    $now,
                );
                $this->access->completeIdempotency($command->submissionId, $now);

                return RegistrationTransactionResult::acceptedWithDelivery(
                    $challengeId,
                    $verificationToken,
                    $expiresAt,
                );
            });
        } catch (DuplicateIdentityContactException) {
            $this->transactions->transactional(function () use ($command, $idempotency, $now): void {
                $claim = $this->access->claimIdempotency(
                    $command->submissionId,
                    'ACCOUNT_REGISTRATION',
                    $idempotency,
                    $now,
                );
                if ($claim !== IdempotencyClaimStatus::CONFLICT) {
                    $this->access->completeIdempotency($command->submissionId, $now);
                }
            });
            return AccountRegistrationResult::accepted();
        }
        if ($transactionResult->conflict) {
            return AccountRegistrationResult::conflict();
        }
        if (
            $transactionResult->challengeId !== null
            && $transactionResult->token !== null
            && $transactionResult->expiresAt !== null
        ) {
            try {
                $this->notifier->send($this->messages->create(
                    $normalizedEmail,
                    $command->locale,
                    $transactionResult->challengeId,
                    $transactionResult->token,
                    $transactionResult->expiresAt,
                ));
            } catch (EmailDeliveryException) {
                $this->logger->log(
                    LogLevel::WARNING,
                    new LogEventName('identity.email.verification.delivery.failed'),
                    ['operation' => 'registration'],
                );
            }
        }

        return AccountRegistrationResult::accepted();
    }

    /** @return non-empty-list<IdentityRateLimitAttempt> */
    private function registrationRateAttempts(
        string $normalizedEmail,
        AccountRegistrationCommand $command,
    ): array {
        $policy = new IdentityRateLimitPolicy(
            $this->configuration->registrationWindowSeconds,
            $this->configuration->registrationMaximumAttempts,
            $this->configuration->rateLimitBlockSeconds,
        );

        return [
            new IdentityRateLimitAttempt(
                IdentityRateLimitScope::ACCOUNT_REGISTRATION_EMAIL,
                $this->fingerprints->generate('registration-email', $normalizedEmail),
                $policy,
            ),
            new IdentityRateLimitAttempt(
                IdentityRateLimitScope::ACCOUNT_REGISTRATION_PEER,
                $this->fingerprints->generate('registration-peer', $command->peer->fingerprintInput()),
                $policy,
            ),
        ];
    }
}
