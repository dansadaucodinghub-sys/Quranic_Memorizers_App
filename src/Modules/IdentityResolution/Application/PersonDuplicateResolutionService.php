<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Application;

use Qmdb\Modules\IdentityAccess\Domain\IdempotencyClaimStatus;
use Qmdb\Modules\IdentityAccess\Domain\Repository\IdentityAccessRepository;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityResolution\Configuration\IdentityResolutionConfiguration;
use Qmdb\Modules\IdentityResolution\Infrastructure\Persistence\MySqlIdentityResolutionRepository;
use Qmdb\Modules\IdentityMultiFactor\Application\StepUpGuard;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\OrganizationAffiliations\Application\OrganizationAffiliationPersonCanonicalizationParticipant;
use Qmdb\Modules\People\Application\PeopleProfileSecurityNotificationService;
use Qmdb\Modules\People\Application\PersonCanonicalizationParticipant;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventSubjectKind;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequest;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequirementGuard;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationSubject;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\PlatformAuthorizationScope;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Database\Transaction\TransactionOptions;
use Qmdb\Shared\Database\Transaction\TransactionRetryPolicy;
use Qmdb\Shared\Time\Clock;

final readonly class PersonDuplicateResolutionService
{
    public function __construct(private MySqlIdentityResolutionRepository $repository, private IdentityAccessRepository $idempotency, private IdentityFingerprintGenerator $fingerprints, private AuthorizationRequirementGuard $authorization, private StepUpGuard $stepUp, private PersonDuplicateResolutionPreflight $preflight, private PersonCanonicalizationParticipant $people, private OrganizationAffiliationPersonCanonicalizationParticipant $affiliations, private PeopleProfileSecurityNotificationService $notifications, private SecurityAuditEventAppender $audit, private IdentityResolutionConfiguration $configuration, private TransactionManager $transactions, private Clock $clock)
    {
    }

    public function resolve(AuthenticatedAccountContext $actor, PersonDuplicateResolutionCommand $command): PersonDuplicateResolutionResult
    {
        if ($command->reference === '' || strlen($command->reference) > $this->configuration->reviewReferenceMaximumBytes || $command->justification === '' || strlen($command->justification) > $this->configuration->reviewJustificationMaximumBytes) {
            throw new \InvalidArgumentException('Duplicate resolution evidence is invalid.');
        }
        $this->authorization->requireAllowed(new AuthorizationRequest(AuthorizationSubject::fromAuthenticatedContext($actor), new PermissionCode('platform.people_duplicates.resolve'), new PlatformAuthorizationScope()));

        return $this->transactions->transactional(function () use ($actor, $command): PersonDuplicateResolutionResult {
            $now = $this->clock->now();
            $idempotency = $this->idempotency->claimIdempotency($command->submission, 'PROFILE_DUPLICATE_RESOLVE', $this->fingerprints->generate('profile-duplicate-resolve-idempotency', $actor->accountInternalId . "\0" . $command->casePublicId . "\0" . $command->canonicalPersonPublicId . "\0" . $command->duplicatePersonPublicId . "\0" . $command->expectedVersion . "\0" . hash('sha256', $command->reference . "\0" . $command->justification)), $now);
            if ($idempotency === IdempotencyClaimStatus::CONFLICT) {
                throw new \DomainException('Duplicate case is unavailable.');
            }
            $case = $this->repository->duplicateCase($command->casePublicId, true);
            if ($case === null) {
                throw new \DomainException('Duplicate case is unavailable.');
            }
            if ($idempotency === IdempotencyClaimStatus::REPLAY && $case['status'] === 'RESOLVED') {
                $canonical = $this->repository->personById((int) $case['canonical_person_id']);

                return new PersonDuplicateResolutionResult((string) $case['public_id'], 'RESOLVED', $canonical === null ? null : (string) $canonical['public_id'], [], true);
            }
            if ($case['status'] !== 'READY_FOR_REVIEW' || (int) $case['version'] !== $command->expectedVersion || (int) $case['reported_by_account_id'] === $actor->accountInternalId) {
                throw new \DomainException('Duplicate case is unavailable.');
            }
            $firstAuthorities = $this->repository->managementAuthorities((int) $case['first_person_id']);
            $secondAuthorities = $this->repository->managementAuthorities((int) $case['second_person_id']);
            $authorities = array_merge($firstAuthorities, $secondAuthorities);
            foreach ($authorities as $authority) {
                if ($authority['account_id'] === $actor->accountInternalId) {
                    throw new \DomainException('Duplicate case is unavailable.');
                }
            }
            if ($firstAuthorities === [] || $secondAuthorities === [] || !$this->repository->authoritySnapshotMatches($case, $authorities)) {
                $this->repository->invalidateConsentRequirements($case, $now);
                if ($firstAuthorities === [] || $secondAuthorities === []) {
                    $blocked = $this->repository->transitionDuplicateCase($case, 'BLOCKED', 'CONSENT_UNAVAILABLE', 'CONSENT_UNAVAILABLE', null, null, null, null, null, $now);
                    $this->repository->recordDuplicateEvent((int) $case['id'], 'RESOLUTION_BLOCKED', null, 'CONSENT_UNAVAILABLE', $now);
                    $this->audit->platform(SecurityEventCode::PERSON_DUPLICATE_BLOCKED, SecurityEventSubjectKind::PERSON_DUPLICATE_CASE, (string) $case['public_id'], $actor->accountId->toString(), $now, ['duplicate_case_public_id' => $case['public_id'], 'conflict_code' => 'CONSENT_UNAVAILABLE'], 'DUPLICATE_RESOLUTION_BLOCKED');
                    $this->notifyAuthorities($authorities, \Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType::PROFILE_DUPLICATE_BLOCKED, (string) $case['public_id'], $now);

                    return new PersonDuplicateResolutionResult((string) $blocked['public_id'], 'BLOCKED', null, ['CONSENT_UNAVAILABLE']);
                }
                $regenerated = $this->repository->transitionDuplicateCase($case, 'CONSENT_REQUIRED', null, null, null, null, null, null, null, $now);
                $this->repository->createConsentRequirements($regenerated, $authorities, $now);

                return new PersonDuplicateResolutionResult((string) $regenerated['public_id'], 'CONSENT_REQUIRED', null);
            }
            $summary = $this->repository->consentSummary($case);
            if ($summary['pending'] !== 0 || $summary['declined'] !== 0 || $summary['approved'] === 0) {
                throw new \DomainException('Duplicate case is unavailable.');
            }
            $canonical = $this->repository->personByPublicId($command->canonicalPersonPublicId, true);
            $source = $this->repository->personByPublicId($command->duplicatePersonPublicId, true);
            if ($canonical === null || $source === null || !in_array((int) $canonical['id'], [(int) $case['first_person_id'], (int) $case['second_person_id']], true) || !in_array((int) $source['id'], [(int) $case['first_person_id'], (int) $case['second_person_id']], true) || (int) $canonical['id'] === (int) $source['id']) {
                throw new \DomainException('Duplicate case is unavailable.');
            }
            $this->stepUp->consumeWithGrant($actor, StepUpAction::PROFILE_DUPLICATE_RESOLVE);
            $plan = $this->preflight->plan((int) $source['id'], (int) $canonical['id'], $this->configuration->maximumAffectedAffiliations);
            if (!$plan->isSafe()) {
                $conflict = $plan->conflictCodes[0];
                $outcome = in_array($conflict, ['IDENTITY_LINK_CONFLICT', 'DEMOGRAPHIC_CONFLICT', 'GEOGRAPHY_CONFLICT', 'MEMORIZER_PROGRESS_CONFLICT', 'ORGANIZATION_AFFILIATION_CONFLICT', 'AFFECTED_RECORD_LIMIT_EXCEEDED'], true) ? $conflict : null;
                $blocked = $this->repository->transitionDuplicateCase($case, 'BLOCKED', $outcome, $conflict, $actor->accountInternalId, null, null, $command->reference, $command->justification, $now);
                $this->repository->recordDuplicateEvent((int) $case['id'], 'RESOLUTION_BLOCKED', $actor->accountInternalId, $conflict, $now);
                $this->audit->platform(SecurityEventCode::PERSON_DUPLICATE_BLOCKED, SecurityEventSubjectKind::PERSON_DUPLICATE_CASE, (string) $case['public_id'], $actor->accountId->toString(), $now, ['duplicate_case_public_id' => $case['public_id'], 'conflict_code' => $conflict, 'affected_affiliation_count' => $plan->affectedRecordCount], 'DUPLICATE_RESOLUTION_BLOCKED');
                $this->notifyAuthorities($authorities, \Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType::PROFILE_DUPLICATE_BLOCKED, (string) $case['public_id'], $now);

                return new PersonDuplicateResolutionResult((string) $blocked['public_id'], 'BLOCKED', null, $plan->conflictCodes);
            }
            $this->people->apply((int) $source['id'], (int) $canonical['id'], $actor->accountInternalId);
            $this->affiliations->apply((int) $source['id'], (int) $canonical['id']);
            $this->repository->revokePendingClaimsForPerson((int) $source['id'], $actor->accountInternalId, $now);
            $this->repository->consolidateVerificationAssertions((int) $source['id'], (int) $canonical['id'], $now);
            $aliasPublicId = $this->repository->retirePersonAndAlias((int) $source['id'], (int) $canonical['id'], $case, $actor->accountInternalId, $now);
            $resolved = $this->repository->transitionDuplicateCase($case, 'RESOLVED', 'CANONICALIZED', null, $actor->accountInternalId, (int) $canonical['id'], (int) $source['id'], $command->reference, $command->justification, $now);
            $this->repository->recordDuplicateEvent((int) $case['id'], 'RESOLVED', $actor->accountInternalId, 'CANONICALIZED', $now);
            $this->audit->platform(SecurityEventCode::PERSON_DUPLICATE_RESOLVED, SecurityEventSubjectKind::PERSON_DUPLICATE_CASE, (string) $case['public_id'], $actor->accountId->toString(), $now, ['duplicate_case_public_id' => $case['public_id'], 'canonical_person_public_id' => $canonical['public_id'], 'duplicate_person_public_id' => $source['public_id'], 'affected_affiliation_count' => $plan->affectedRecordCount], 'PERSON_DUPLICATE_RESOLVED');
            $this->audit->platform(SecurityEventCode::PERSON_CANONICALIZED, SecurityEventSubjectKind::PERSON_ALIAS, $aliasPublicId, $actor->accountId->toString(), $now, ['canonical_person_public_id' => $canonical['public_id'], 'duplicate_person_public_id' => $source['public_id']], 'PERSON_CANONICALIZED');
            $this->notifyAuthorities($authorities, \Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType::PROFILE_DUPLICATE_RESOLVED, (string) $case['public_id'], $now);
            $this->idempotency->completeIdempotency($command->submission, $now);

            return new PersonDuplicateResolutionResult((string) $resolved['public_id'], 'RESOLVED', (string) $canonical['public_id']);
        }, TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
    }

    /**
     * @param list<array{person_id:int,account_id:int,authority_type:string,guardianship_id:?int}> $authorities
     */
    private function notifyAuthorities(array $authorities, \Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType $type, string $casePublicId, \DateTimeImmutable $now): void
    {
        $accountIds = [];
        foreach ($authorities as $authority) {
            $accountIds[$authority['account_id']] = true;
        }
        foreach (array_keys($accountIds) as $accountId) {
            $this->notifications->create($accountId, $type, $casePublicId, $now);
        }
    }
}
