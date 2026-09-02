<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Application;

use Qmdb\Modules\IdentityAccess\Domain\IdempotencyClaimStatus;
use Qmdb\Modules\IdentityAccess\Domain\Repository\IdentityAccessRepository;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitAttempt;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitPolicy;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitScope;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Modules\IdentityResolution\Configuration\IdentityResolutionConfiguration;
use Qmdb\Modules\IdentityResolution\Infrastructure\Persistence\MySqlIdentityResolutionRepository;
use Qmdb\Modules\IdentityMultiFactor\Application\StepUpGuard;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\People\Application\PeopleProfileSecurityNotificationService;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventSubjectKind;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Database\Transaction\TransactionOptions;
use Qmdb\Shared\Database\Transaction\TransactionRetryPolicy;
use Qmdb\Shared\Time\Clock;

final readonly class PersonDuplicateConsentService
{
    public function __construct(private MySqlIdentityResolutionRepository $repository, private IdentityAccessRepository $idempotency, private IdentityRateLimiter $rateLimiter, private IdentityFingerprintGenerator $fingerprints, private StepUpGuard $stepUp, private PeopleProfileSecurityNotificationService $notifications, private SecurityAuditEventAppender $audit, private IdentityResolutionConfiguration $configuration, private TransactionManager $transactions, private Clock $clock)
    {
    }

    public function decide(AuthenticatedAccountContext $actor, PersonDuplicateConsentCommand $command): PersonDuplicateConsentResult
    {
        if (!$this->rateLimiter->consume([new IdentityRateLimitAttempt(IdentityRateLimitScope::PROFILE_DUPLICATE_MUTATION_ACCOUNT, $this->fingerprints->generate('profile-duplicate-consent-account', (string) $actor->accountInternalId), $this->policy())], $this->clock->now())->allowed) {
            throw new \DomainException('Duplicate consent is temporarily unavailable.');
        }

        return $this->transactions->transactional(function () use ($actor, $command): PersonDuplicateConsentResult {
            $now = $this->clock->now();
            $idempotency = $this->idempotency->claimIdempotency($command->submission, 'PROFILE_DUPLICATE_CONSENT', $this->fingerprints->generate('profile-duplicate-consent-idempotency', $actor->accountInternalId . "\0" . $command->requirementPublicId . "\0" . $command->decision->value . "\0" . $command->expectedVersion), $now);
            if ($idempotency === IdempotencyClaimStatus::CONFLICT) {
                throw new \DomainException('Duplicate consent conflicts with a prior request.');
            }
            $requirement = $this->repository->activeConsentRequirement($command->requirementPublicId, $actor->accountInternalId, true);
            if ($requirement === null) {
                throw new \DomainException('Duplicate consent is unavailable.');
            }
            $case = $this->repository->duplicateCaseById((int) $requirement['case_id'], true);
            if ($case === null) {
                throw new \DomainException('Duplicate consent is unavailable.');
            }
            if ($idempotency === IdempotencyClaimStatus::REPLAY) {
                return new PersonDuplicateConsentResult((string) $case['public_id'], (string) $case['status'], false, true);
            }
            if ((int) $requirement['version'] !== $command->expectedVersion || $case['status'] !== 'CONSENT_REQUIRED') {
                throw new \DomainException('Duplicate consent is unavailable.');
            }
            $firstAuthorities = $this->repository->managementAuthorities((int) $case['first_person_id']);
            $secondAuthorities = $this->repository->managementAuthorities((int) $case['second_person_id']);
            $current = array_merge($firstAuthorities, $secondAuthorities);
            if (count($current) > $this->configuration->maximumConsentAuthorities) {
                throw new \DomainException('Duplicate consent is unavailable.');
            }
            if (!$this->repository->authoritySnapshotMatches($case, $current)) {
                $this->repository->invalidateConsentRequirements($case, $now);
                if ($firstAuthorities === [] || $secondAuthorities === []) {
                    $updated = $this->repository->transitionDuplicateCase($case, 'BLOCKED', 'CONSENT_UNAVAILABLE', 'CONSENT_UNAVAILABLE', null, null, null, null, null, $now);
                    $this->repository->recordDuplicateEvent((int) $case['id'], 'RESOLUTION_BLOCKED', null, 'CONSENT_UNAVAILABLE', $now);

                    return new PersonDuplicateConsentResult((string) $updated['public_id'], 'BLOCKED', true, false);
                }
                $updated = $this->repository->transitionDuplicateCase($case, 'CONSENT_REQUIRED', null, null, null, null, null, null, null, $now);
                $this->repository->createConsentRequirements($updated, $current, $now);

                return new PersonDuplicateConsentResult((string) $updated['public_id'], 'CONSENT_REQUIRED', true, false);
            }
            $valid = false;
            foreach ($current as $authority) {
                if ($authority['person_id'] === (int) $requirement['person_id'] && $authority['account_id'] === $actor->accountInternalId && $authority['authority_type'] === $requirement['authority_type'] && (int) ($authority['guardianship_id'] ?? 0) === (int) $requirement['guardianship_id']) {
                    $valid = true;
                    break;
                }
            }
            if (!$valid) {
                throw new \DomainException('Duplicate consent is unavailable.');
            }
            $this->stepUp->consumeWithGrant($actor, StepUpAction::PROFILE_DUPLICATE_CONSENT);
            $this->repository->decideConsent($requirement, $command->decision->value, $now);
            $summary = $this->repository->consentSummary($case);
            if ($command->decision->value === 'DECLINED' || $summary['declined'] > 0) {
                $updated = $this->repository->transitionDuplicateCase($case, 'BLOCKED', 'CONSENT_DECLINED', 'CONSENT_DECLINED', null, null, null, null, null, $now);
                $this->repository->recordDuplicateEvent((int) $case['id'], 'CONSENT_DECLINED', $actor->accountInternalId, 'CONSENT_DECLINED', $now);
                $this->audit->platform(SecurityEventCode::PERSON_DUPLICATE_CONSENT_DECLINED, SecurityEventSubjectKind::PERSON_DUPLICATE_CASE, (string) $case['public_id'], $actor->accountId->toString(), $now, ['duplicate_case_public_id' => $case['public_id']], 'CONSENT_DECLINED');
                $this->notifications->create($actor->accountInternalId, \Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType::PROFILE_DUPLICATE_CONSENT_DECLINED, (string) $case['public_id'], $now);
            } else {
                $status = $summary['pending'] === 0 && $summary['approved'] > 0 ? 'READY_FOR_REVIEW' : 'CONSENT_REQUIRED';
                $updated = $this->repository->transitionDuplicateCase($case, $status, null, null, null, null, null, null, null, $now);
                $this->repository->recordDuplicateEvent((int) $case['id'], 'CONSENT_APPROVED', $actor->accountInternalId, 'CONSENT_APPROVED', $now);
                $this->audit->platform(SecurityEventCode::PERSON_DUPLICATE_CONSENT_APPROVED, SecurityEventSubjectKind::PERSON_DUPLICATE_CASE, (string) $case['public_id'], $actor->accountId->toString(), $now, ['duplicate_case_public_id' => $case['public_id']], 'CONSENT_APPROVED');
            }
            $this->idempotency->completeIdempotency($command->submission, $now);

            return new PersonDuplicateConsentResult((string) $updated['public_id'], (string) $updated['status'], false, false);
        }, TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
    }

    private function policy(): IdentityRateLimitPolicy
    {
        return new IdentityRateLimitPolicy($this->configuration->duplicateWindowSeconds, $this->configuration->duplicateMaximumAttempts, $this->configuration->duplicateWindowSeconds);
    }
}
