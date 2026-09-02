<?php

declare(strict_types=1);

namespace Qmdb\Modules\OrganizationAffiliations\Application;

use DateTimeImmutable;
use Qmdb\Modules\IdentityAccess\Domain\IdempotencyClaimStatus;
use Qmdb\Modules\IdentityAccess\Domain\Repository\IdentityAccessRepository;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitAttempt;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitPolicy;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitScope;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Modules\IdentityMultiFactor\Application\StepUpGuard;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationId;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\Repository\AccountSecurityNotificationRepository;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\SecurityNotificationDeduplicationKeyFactory;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\OrganizationAffiliations\Configuration\OrganizationAffiliationsConfiguration;
use Qmdb\Modules\OrganizationAffiliations\Domain\OrganizationAffiliationCodeGenerator;
use Qmdb\Modules\OrganizationAffiliations\Domain\OrganizationAffiliationSubmissionId;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventSubjectKind;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequest;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequirementGuard;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationSubject;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\WorkspaceAuthorizationScope;
use Qmdb\Modules\TenancyContext\Domain\AccountWorkspaceTenantContext;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Database\Transaction\TransactionOptions;
use Qmdb\Shared\Database\Transaction\TransactionRetryPolicy;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Time\Clock;

/** @phpstan-import-type Row from OrganizationAffiliationRepository */
final readonly class OrganizationAffiliationService
{
    public function __construct(
        private OrganizationAffiliationRepository $repository,
        private IdentityAccessRepository $idempotency,
        private IdentityRateLimiter $rateLimits,
        private IdentityFingerprintGenerator $fingerprints,
        private AuthorizationRequirementGuard $authorization,
        private StepUpGuard $stepUp,
        private SecurityAuditEventAppender $audit,
        private AccountSecurityNotificationRepository $notifications,
        private SecurityNotificationDeduplicationKeyFactory $notificationKeys,
        private OrganizationAffiliationCodeGenerator $codes,
        private OrganizationAffiliationsConfiguration $configuration,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {
    }

    /** @return Row */
    public function request(AuthenticatedAccountContext $actor, AccountWorkspaceTenantContext $context, OrganizationAffiliationSubmissionId $submission, string $organizationPublicId, string $personRegistryCode, OrganizationAffiliationInput $input, ?string $correlationId = null): array
    {
        $this->authorize($actor, $context, 'workspace.organization_affiliations.manage');
        $this->rate($actor, IdentityRateLimitScope::ORGANIZATION_AFFILIATION_REQUEST_ACCOUNT, IdentityRateLimitScope::ORGANIZATION_AFFILIATION_REQUEST_PEER, $this->configuration->requestWindowSeconds, $this->configuration->requestMaximumAttempts);
        return $this->transactions->transactional(function () use ($actor, $context, $submission, $organizationPublicId, $personRegistryCode, $input, $correlationId): array {
            $now = $this->clock->now();
            $claim = $this->claim($submission, 'ORGANIZATION_AFFILIATION_REQUEST', $actor, $context, hash('sha256', strtoupper($personRegistryCode) . "\0" . $organizationPublicId . "\0" . $this->assignmentDigest($input)));
            if ($claim !== IdempotencyClaimStatus::CLAIMED) {
                throw new \DomainException('Affiliation request is unavailable.');
            }
            $organization = $this->required($this->repository->organization($context->tenant(), $organizationPublicId, true));
            if ($organization['status'] !== 'ACTIVE') {
                throw new \DomainException('Organization is unavailable.');
            }
            if (preg_match('/\AQMP-[A-Z2-9]{16}\z/', strtoupper($personRegistryCode)) !== 1) {
                throw new \DomainException('Person is unavailable.');
            }
            $candidate = $this->required($this->repository->candidateByRegistryCode(strtoupper($personRegistryCode)));
            if ($this->repository->notificationTargets((int) $candidate['id']) === []) {
                throw new \DomainException('Person is unavailable.');
            }
            $definitions = $this->validateAssignments($context, $organization, $candidate, $input);
            if ($this->hasLeadership($definitions)) {
                $this->authorize($actor, $context, 'workspace.organization_leadership.manage');
                $this->stepUp->consumeWithGrant($actor, StepUpAction::ORGANIZATION_LEADERSHIP_ASSIGN);
            }
            $affiliation = $this->repository->createRequest($context->tenant(), $organization, $candidate, $actor->accountInternalId, UuidV7::generate()->toString(), $this->codes->generate(), $now->modify('+' . $this->configuration->requestTtlSeconds . ' seconds'), $input->roles, $input->units, $now);
            $this->idempotency->completeIdempotency($submission, $now);
            $this->audit->workspace(SecurityEventCode::ORGANIZATION_AFFILIATION_REQUESTED, $context->workspacePublicId(), SecurityEventSubjectKind::ORGANIZATION_AFFILIATION, (string) $affiliation['public_id'], $actor->accountId->toString(), $now, ['affiliation_public_id' => $affiliation['public_id'],'organization_public_id' => $organizationPublicId,'actor_authority' => 'ORGANIZATION_MANAGER','role_codes_changed' => array_column($input->roles, 'code')], 'AFFILIATION_REQUESTED', $correlationId);
            $this->notify((int)$candidate['id'], AccountSecurityNotificationType::ORGANIZATION_AFFILIATION_REQUESTED, (string)$affiliation['public_id'], $now);
            return ['public_id' => $affiliation['public_id'],'affiliation_code' => $affiliation['affiliation_code'],'status' => 'PENDING_ACCEPTANCE','version' => (int)$affiliation['version']];
        }, TransactionOptions::readWrite(retryPolicy:new TransactionRetryPolicy(3, 15, 150)));
    }

    /** @return Row */
    public function respond(AuthenticatedAccountContext $actor, OrganizationAffiliationSubmissionId $submission, string $affiliationPublicId, string $operation, ?string $correlationId = null): array
    {
        if (!in_array($operation, ['ACCEPT','DECLINE','LEAVE'], true)) {
            throw new \InvalidArgumentException('Affiliation response operation is invalid.');
        }
        $this->rate($actor, IdentityRateLimitScope::ORGANIZATION_AFFILIATION_RESPONSE_ACCOUNT, IdentityRateLimitScope::ORGANIZATION_AFFILIATION_RESPONSE_PEER, $this->configuration->responseWindowSeconds, $this->configuration->responseMaximumAttempts);
        return $this->transactions->transactional(function () use ($actor, $submission, $affiliationPublicId, $operation, $correlationId): array {
            $now = $this->clock->now();
            $affiliation = $this->required($this->repository->responseAffiliation($actor->accountInternalId, $affiliationPublicId, true));
            $authority = $this->required($this->repository->responseAuthority($actor->accountInternalId, (int)$affiliation['person_id']));
            $idempotencyOperation = 'ORGANIZATION_AFFILIATION_' . ($operation === 'ACCEPT' ? 'ACCEPT' : ($operation === 'DECLINE' ? 'DECLINE' : 'LEAVE'));
            $claim = $this->idempotency->claimIdempotency($submission, $idempotencyOperation, $this->fingerprints->generate('organization-affiliation-idempotency', $actor->accountInternalId . "\0" . $idempotencyOperation . "\0" . $affiliationPublicId), $now);
            if ($claim !== IdempotencyClaimStatus::CLAIMED) {
                throw new \DomainException('Affiliation response is unavailable.');
            }
            if ($operation === 'ACCEPT') {
                if ($affiliation['status'] !== 'PENDING_ACCEPTANCE' || new DateTimeImmutable((string)$affiliation['request_expires_at']) <= $now) {
                    throw new \DomainException('Affiliation request is unavailable.');
                }
                $assignments = $this->repository->assignments(\Qmdb\Modules\Tenancy\Application\TenantContext::trusted((int)$affiliation['workspace_id'], \Qmdb\Modules\Tenancy\Domain\Value\WorkspaceId::fromString((string)$affiliation['workspace_public_id'])), $affiliation);
                if (count(array_filter($assignments, static fn(array $assignment): bool=>$assignment['status'] === 'PROPOSED')) < 1 || count(array_filter($assignments, static fn(array $assignment): bool=>$assignment['status'] === 'PROPOSED' && (int)$assignment['is_primary'] === 1)) !== 1) {
                    throw new \DomainException('Affiliation request is invalid.');
                }foreach ($assignments as $assignment) {
                    if ($assignment['status'] === 'PROPOSED' && $assignment['required_person_role_type'] !== null && !$this->repository->personHasRequiredRole((int)$affiliation['person_id'], (string)$assignment['required_person_role_type'])) {
                        throw new \DomainException('Affiliation request is unavailable.');
                    }
                }$leadership = in_array('LEADERSHIP', array_column($assignments, 'sensitivity_level'), true);
                $this->stepUp->consumeWithGrant($actor, $leadership ? StepUpAction::ORGANIZATION_AFFILIATION_ACCEPT_LEADERSHIP : StepUpAction::ORGANIZATION_AFFILIATION_ACCEPT);
                $next = $this->repository->transition($affiliation, 'ACTIVE', $actor->accountInternalId, (string)$authority['authority'], $authority['guardianship_id'] === null ? null : (int)$authority['guardianship_id'], 'AFFILIATION_ACCEPTED', (string)$authority['authority'], $actor->accountInternalId, $authority['guardianship_id'] === null ? null : (int)$authority['guardianship_id'], $correlationId, $now);
                $this->repository->activateProposedAssignments($next, $now);
                $event = SecurityEventCode::ORGANIZATION_AFFILIATION_ACCEPTED;
                $type = AccountSecurityNotificationType::ORGANIZATION_AFFILIATION_ACCEPTED;
            } elseif ($operation === 'DECLINE') {
                if ($affiliation['status'] !== 'PENDING_ACCEPTANCE' || new DateTimeImmutable((string)$affiliation['request_expires_at']) <= $now) {
                    throw new \DomainException('Affiliation request is unavailable.');
                }$next = $this->repository->transition($affiliation, 'DECLINED', $actor->accountInternalId, (string)$authority['authority'], $authority['guardianship_id'] === null ? null : (int)$authority['guardianship_id'], 'AFFILIATION_DECLINED', (string)$authority['authority'], $actor->accountInternalId, $authority['guardianship_id'] === null ? null : (int)$authority['guardianship_id'], $correlationId, $now);
                $this->repository->cancelProposedAssignments($next, $now);
                $event = SecurityEventCode::ORGANIZATION_AFFILIATION_DECLINED;
                $type = AccountSecurityNotificationType::ORGANIZATION_AFFILIATION_DECLINED;
            } else {
                if (!in_array($affiliation['status'], ['ACTIVE','SUSPENDED'], true)) {
                    throw new \DomainException('Affiliation is unavailable.');
                }$this->stepUp->consumeWithGrant($actor, StepUpAction::ORGANIZATION_AFFILIATION_LEAVE);
                $reason = $authority['authority'] === 'GUARDIAN' ? 'GUARDIAN_WITHDREW' : 'PERSON_LEFT';
                $next = $this->repository->transition($affiliation, 'ENDED', null, null, null, $reason, (string)$authority['authority'], $actor->accountInternalId, $authority['guardianship_id'] === null ? null : (int)$authority['guardianship_id'], $correlationId, $now);
                $this->repository->removeActiveAssignments($next, $actor->accountInternalId, $now);
                $event = SecurityEventCode::ORGANIZATION_AFFILIATION_LEFT;
                $type = AccountSecurityNotificationType::ORGANIZATION_AFFILIATION_ENDED;
            }
            $this->idempotency->completeIdempotency($submission, $now);
            $this->audit->workspace($event, (string)$affiliation['workspace_public_id'], SecurityEventSubjectKind::ORGANIZATION_AFFILIATION, (string)$affiliation['public_id'], $actor->accountId->toString(), $now, ['affiliation_public_id' => $affiliation['public_id'],'actor_authority' => $authority['authority']], null, $correlationId);
            $this->notify((int)$affiliation['person_id'], $type, (string)$affiliation['public_id'], $now);
            return ['public_id' => $next['public_id'],'status' => $next['status'],'version' => $next['version']];
        }, TransactionOptions::readWrite(retryPolicy:new TransactionRetryPolicy(3, 15, 150)));
    }

    /** @return Row */
    public function organizationTransition(AuthenticatedAccountContext $actor, AccountWorkspaceTenantContext $context, OrganizationAffiliationSubmissionId $submission, string $organizationPublicId, string $affiliationPublicId, string $operation, int $expectedVersion, string $reasonCode, ?string $correlationId = null): array
    {
        $this->authorize($actor, $context, 'workspace.organization_affiliations.manage');
        $this->rate($actor, IdentityRateLimitScope::ORGANIZATION_AFFILIATION_MUTATION_ACCOUNT, IdentityRateLimitScope::ORGANIZATION_AFFILIATION_MUTATION_PEER, $this->configuration->mutationWindowSeconds, $this->configuration->mutationMaximumAttempts);
        return $this->transactions->transactional(function () use ($actor, $context, $submission, $organizationPublicId, $affiliationPublicId, $operation, $expectedVersion, $reasonCode, $correlationId): array {
            $now = $this->clock->now();
            $organization = $this->required($this->repository->organization($context->tenant(), $organizationPublicId, true));
            if ($organization['status'] !== 'ACTIVE') {
                throw new \DomainException('Organization is unavailable.');
            }$affiliation = $this->required($this->repository->affiliation($context->tenant(), $organization, $affiliationPublicId, true));
            if ((int)$affiliation['version'] !== $expectedVersion) {
                throw new \DomainException('Affiliation is stale.');
            }$target = match ($operation) {
                'WITHDRAW'=>'WITHDRAWN','SUSPEND'=>'SUSPENDED','RESUME'=>'ACTIVE','END'=>'ENDED',default=>throw new \InvalidArgumentException('Affiliation lifecycle operation is invalid.')
            };
            $expected = ['WITHDRAW' => 'PENDING_ACCEPTANCE','SUSPEND' => 'ACTIVE','RESUME' => 'SUSPENDED','END' => ['ACTIVE','SUSPENDED']][$operation];
            if (is_array($expected) ? !in_array($affiliation['status'], $expected, true) : $affiliation['status'] !== $expected) {
                throw new \DomainException('Affiliation lifecycle operation is unavailable.');
            }$action = match ($operation) {
                'SUSPEND'=>StepUpAction::ORGANIZATION_AFFILIATION_SUSPEND,'RESUME'=>StepUpAction::ORGANIZATION_AFFILIATION_RESUME,'END'=>StepUpAction::ORGANIZATION_AFFILIATION_END,default=>null
            };
            if ($action !== null) {
                $this->stepUp->consumeWithGrant($actor, $action);
            }$claim = $this->claim($submission, 'ORGANIZATION_AFFILIATION_' . ($operation === 'WITHDRAW' ? 'WITHDRAW' : $operation), $actor, $context, $affiliationPublicId . "\0" . $expectedVersion);
            if ($claim !== IdempotencyClaimStatus::CLAIMED) {
                throw new \DomainException('Affiliation operation is unavailable.');
            }$reasons = ['WITHDRAW' => 'REQUEST_WITHDRAWN','SUSPEND' => $reasonCode,'RESUME' => 'AFFILIATION_RESUMED','END' => 'ORGANIZATION_ENDED'];
            $next = $this->repository->transition($affiliation, $target, null, null, null, $reasons[$operation], 'ORGANIZATION_MANAGER', $actor->accountInternalId, null, $correlationId, $now);
            if ($operation === 'WITHDRAW') {
                $this->repository->cancelProposedAssignments($next, $now);
            }if ($operation === 'END') {
                $this->repository->removeActiveAssignments($next, $actor->accountInternalId, $now);
            }$this->idempotency->completeIdempotency($submission, $now);
            $event = match ($operation) {
                'WITHDRAW'=>SecurityEventCode::ORGANIZATION_AFFILIATION_WITHDRAWN,'SUSPEND'=>SecurityEventCode::ORGANIZATION_AFFILIATION_SUSPENDED,'RESUME'=>SecurityEventCode::ORGANIZATION_AFFILIATION_RESUMED,'END'=>SecurityEventCode::ORGANIZATION_AFFILIATION_ENDED
            };
            $type = match ($operation) {
                'SUSPEND'=>AccountSecurityNotificationType::ORGANIZATION_AFFILIATION_SUSPENDED,'RESUME'=>AccountSecurityNotificationType::ORGANIZATION_AFFILIATION_RESUMED,default=>AccountSecurityNotificationType::ORGANIZATION_AFFILIATION_ENDED
            };
            $this->audit->workspace($event, $context->workspacePublicId(), SecurityEventSubjectKind::ORGANIZATION_AFFILIATION, $affiliationPublicId, $actor->accountId->toString(), $now, ['affiliation_public_id' => $affiliationPublicId,'organization_public_id' => $organizationPublicId,'actor_authority' => 'ORGANIZATION_MANAGER'], $reasons[$operation], $correlationId);
            $this->notify((int)$affiliation['person_id'], $type, $affiliationPublicId, $now);
            return ['public_id' => $next['public_id'],'status' => $next['status'],'version' => $next['version']];
        }, TransactionOptions::readWrite(retryPolicy:new TransactionRetryPolicy(3, 15, 150)));
    }

    /** @return Row */
    public function updateAssignments(AuthenticatedAccountContext $actor, AccountWorkspaceTenantContext $context, OrganizationAffiliationSubmissionId $submission, string $organizationPublicId, string $affiliationPublicId, int $expectedVersion, OrganizationAffiliationInput $input, ?string $correlationId = null): array
    {
        $this->authorize($actor, $context, 'workspace.organization_affiliation_assignments.manage');
        $this->rate($actor, IdentityRateLimitScope::ORGANIZATION_AFFILIATION_MUTATION_ACCOUNT, IdentityRateLimitScope::ORGANIZATION_AFFILIATION_MUTATION_PEER, $this->configuration->mutationWindowSeconds, $this->configuration->mutationMaximumAttempts);

        return $this->transactions->transactional(function () use ($actor, $context, $submission, $organizationPublicId, $affiliationPublicId, $expectedVersion, $input, $correlationId): array {
            $now = $this->clock->now();
            $organization = $this->required($this->repository->organization($context->tenant(), $organizationPublicId, true));
            if ($organization['status'] !== 'ACTIVE') {
                throw new \DomainException('Organization is unavailable.');
            }
            $affiliation = $this->required($this->repository->affiliation($context->tenant(), $organization, $affiliationPublicId, true));
            if (!in_array($affiliation['status'], ['ACTIVE', 'SUSPENDED'], true) || (int) $affiliation['version'] !== $expectedVersion) {
                throw new \DomainException('Affiliation is stale or unavailable.');
            }

            $definitions = $this->validateAssignments($context, $organization, ['id' => (int) $affiliation['person_id']], $input);
            $previous = $this->repository->assignments($context->tenant(), $affiliation);
            $previousLeadership = $this->hasLeadership(array_filter($previous, static fn (array $assignment): bool => $assignment['status'] === 'ACTIVE'));
            $nextLeadership = $this->hasLeadership($definitions);
            if ($previousLeadership || $nextLeadership) {
                $this->authorize($actor, $context, 'workspace.organization_leadership.manage');
                $this->stepUp->consumeWithGrant(
                    $actor,
                    $nextLeadership && !$previousLeadership
                        ? StepUpAction::ORGANIZATION_LEADERSHIP_ASSIGN
                        : StepUpAction::ORGANIZATION_LEADERSHIP_REMOVE,
                );
            }

            $claim = $this->claim($submission, 'ORGANIZATION_AFFILIATION_ASSIGNMENTS_UPDATE', $actor, $context, $affiliationPublicId . "\0" . $expectedVersion . "\0" . $this->assignmentDigest($input));
            if ($claim !== IdempotencyClaimStatus::CLAIMED) {
                throw new \DomainException('Affiliation assignment update is unavailable.');
            }

            $this->repository->replaceAssignments($context->tenant(), $organization, $affiliation, $actor->accountInternalId, $input->roles, $input->units, $now);
            $next = $this->repository->incrementVersion($affiliation, $now);
            $this->idempotency->completeIdempotency($submission, $now);
            $leadershipChanged = $previousLeadership !== $nextLeadership;
            $this->audit->workspace(
                SecurityEventCode::ORGANIZATION_AFFILIATION_ASSIGNMENTS_UPDATED,
                $context->workspacePublicId(),
                SecurityEventSubjectKind::ORGANIZATION_AFFILIATION,
                $affiliationPublicId,
                $actor->accountId->toString(),
                $now,
                ['affiliation_public_id' => $affiliationPublicId, 'organization_public_id' => $organizationPublicId, 'actor_authority' => 'ORGANIZATION_MANAGER', 'leadership_changed' => $leadershipChanged, 'role_codes_changed' => array_column($input->roles, 'code')],
                'ASSIGNMENTS_UPDATED',
                $correlationId,
            );
            $this->notify(
                (int) $affiliation['person_id'],
                $leadershipChanged ? AccountSecurityNotificationType::ORGANIZATION_LEADERSHIP_CHANGED : AccountSecurityNotificationType::ORGANIZATION_AFFILIATION_ASSIGNMENTS_CHANGED,
                $affiliationPublicId,
                $now,
            );

            return ['public_id' => $next['public_id'], 'status' => $next['status'], 'version' => $next['version']];
        }, TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
    }

    /** @return array{organization: Row, affiliations: list<Row>} */
    public function organizationRoster(AuthenticatedAccountContext $actor, AccountWorkspaceTenantContext $context, string $organizationPublicId, string $status = 'ALL'): array
    {
        $this->authorize($actor, $context, 'workspace.organization_affiliations.view');
        if (!in_array($status, ['ALL', 'PENDING_ACCEPTANCE', 'ACTIVE', 'SUSPENDED', 'ENDED', 'DECLINED', 'WITHDRAWN', 'EXPIRED'], true)) {
            throw new \InvalidArgumentException('Affiliation status filter is invalid.');
        }

        $organization = $this->required($this->repository->organization($context->tenant(), $organizationPublicId));

        return [
            'organization' => $organization,
            'affiliations' => $this->repository->roster($context->tenant(), $organization, $status, $this->configuration->maximumPageSize),
        ];
    }

    /** @return array<string, int|string|null|list<Row>>|null */
    public function organizationAffiliation(AuthenticatedAccountContext $actor, AccountWorkspaceTenantContext $context, string $organizationPublicId, string $affiliationPublicId): ?array
    {
        $this->authorize($actor, $context, 'workspace.organization_affiliations.view');
        $organization = $this->required($this->repository->organization($context->tenant(), $organizationPublicId));
        $affiliation = $this->repository->affiliation($context->tenant(), $organization, $affiliationPublicId);
        if ($affiliation === null) {
            return null;
        }
        $affiliation['assignments'] = $this->repository->assignments($context->tenant(), $affiliation);

        return $affiliation;
    }

    /** @return list<Row> */
    public function accountAffiliations(AuthenticatedAccountContext $actor): array
    {
        return $this->repository->accountAffiliations($actor->accountInternalId);
    }

    public function maintain(): int
    {
        $now = $this->clock->now();
        $processed = 0;
        return $this->transactions->transactional(function () use ($now, &$processed): int {
            foreach ($this->repository->expiredPending($this->configuration->maintenanceBatchSize, $now) as $affiliation) {
                if ($affiliation['status'] !== 'PENDING_ACCEPTANCE' || new DateTimeImmutable((string)$affiliation['request_expires_at']) > $now) {
                    continue;
                }$next = $this->repository->transition($affiliation, 'EXPIRED', null, null, null, 'REQUEST_EXPIRED', 'SYSTEM', null, null, null, $now);
                $this->repository->cancelProposedAssignments($next, $now);
                $this->audit->workspace(SecurityEventCode::ORGANIZATION_AFFILIATION_EXPIRED, (string)$next['workspace_public_id'], SecurityEventSubjectKind::ORGANIZATION_AFFILIATION, (string)$next['public_id'], null, $now, ['affiliation_public_id' => $next['public_id'],'actor_authority' => 'SYSTEM'], 'REQUEST_EXPIRED');
                $this->notify((int)$next['person_id'], AccountSecurityNotificationType::ORGANIZATION_AFFILIATION_EXPIRED, (string)$next['public_id'], $now);
                ++$processed;
            }return $processed;
        }, TransactionOptions::readWrite(retryPolicy:new TransactionRetryPolicy(3, 15, 150)));
    }

    /**
     * @param Row $organization
     * @param Row $candidate
     *
     * @return list<Row>
     */
    private function validateAssignments(AccountWorkspaceTenantContext $context, array $organization, array $candidate, OrganizationAffiliationInput $input): array
    {
        $definitions = $this->repository->activeRoleDefinitions(array_column($input->roles, 'code'));
        if (count($definitions) !== count($input->roles)) {
            throw new \DomainException('Affiliation role is unavailable.');
        }
        foreach ($definitions as $definition) {
            if ($definition['required_person_role_type'] !== null && !$this->repository->personHasRequiredRole((int)$candidate['id'], (string)$definition['required_person_role_type'])) {
                throw new \DomainException('Person is unavailable.');
            }
        }$units = $this->repository->activeUnits($context->tenant(), $organization, array_column($input->units, 'public_id'));
        if (count($units) !== count($input->units)) {
            throw new \DomainException('Affiliation unit is unavailable.');
        }return $definitions;
    }
    /** @param array<int, Row> $definitions */
    private function hasLeadership(array $definitions): bool
    {
        return in_array('LEADERSHIP', array_column($definitions, 'sensitivity_level'), true);
    }
    private function authorize(AuthenticatedAccountContext $actor, AccountWorkspaceTenantContext $context, string $permission): void
    {
        $this->authorization->requireAllowed(new AuthorizationRequest(AuthorizationSubject::fromAuthenticatedContext($actor), new PermissionCode($permission), new WorkspaceAuthorizationScope($context)));
    }
    private function rate(AuthenticatedAccountContext $actor, IdentityRateLimitScope $accountScope, IdentityRateLimitScope $peerScope, int $window, int $attempts): void
    {
        $policy = new IdentityRateLimitPolicy($window, $attempts, $window);
        if (!$this->rateLimits->consume([new IdentityRateLimitAttempt($accountScope, $this->fingerprints->generate('organization-affiliation-account', (string)$actor->accountInternalId), $policy),new IdentityRateLimitAttempt($peerScope, $this->fingerprints->generate('organization-affiliation-session', (string)$actor->sessionInternalId), $policy)], $this->clock->now())->allowed) {
            throw new \DomainException('Affiliation operation is temporarily unavailable.');
        }
    }
    private function claim(OrganizationAffiliationSubmissionId $submission, string $operation, AuthenticatedAccountContext $actor, AccountWorkspaceTenantContext $context, string $content): IdempotencyClaimStatus
    {
        return $this->idempotency->claimIdempotency($submission, $operation, $this->fingerprints->generate('organization-affiliation-idempotency', $actor->accountInternalId . "\0" . $context->workspaceInternalId . "\0" . $operation . "\0" . hash('sha256', $content)), $this->clock->now());
    }
    private function notify(int $personId, AccountSecurityNotificationType $type, string $sourcePublicId, DateTimeImmutable $now): void
    {
        foreach ($this->repository->notificationTargets($personId) as $target) {
            $this->notifications->createPendingIntent(AccountSecurityNotificationId::generate(), (int)$target['account_id'], (int)$target['email_id'], $type, $this->notificationKeys->forEvent($type, $sourcePublicId, (string)$target['account_public_id']), (string)$target['locale'], 3, $now);
        }
    }
    private function assignmentDigest(OrganizationAffiliationInput $input): string
    {
        return hash('sha256', json_encode(['roles' => $input->roles,'units' => $input->units], JSON_THROW_ON_ERROR));
    }
    /**
     * @param Row|null $value
     *
     * @return Row
     */
    private function required(?array $value): array
    {
        if ($value === null) {
            throw new \DomainException('Affiliation is unavailable.');
        }return $value;
    }
}
