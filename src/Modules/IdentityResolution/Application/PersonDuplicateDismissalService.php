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

final readonly class PersonDuplicateDismissalService
{
    public function __construct(private MySqlIdentityResolutionRepository $repository, private IdentityAccessRepository $idempotency, private IdentityFingerprintGenerator $fingerprints, private AuthorizationRequirementGuard $authorization, private StepUpGuard $stepUp, private SecurityAuditEventAppender $audit, private IdentityResolutionConfiguration $configuration, private TransactionManager $transactions, private Clock $clock)
    {
    }

    public function dismiss(AuthenticatedAccountContext $actor, PersonDuplicateDismissalCommand $command): void
    {
        if ($command->reference === '' || strlen($command->reference) > $this->configuration->reviewReferenceMaximumBytes || $command->justification === '' || strlen($command->justification) > $this->configuration->reviewJustificationMaximumBytes) {
            throw new \InvalidArgumentException('Duplicate dismissal evidence is invalid.');
        }
        $this->authorization->requireAllowed(new AuthorizationRequest(AuthorizationSubject::fromAuthenticatedContext($actor), new PermissionCode('platform.people_duplicates.resolve'), new PlatformAuthorizationScope()));
        $this->transactions->transactional(function () use ($actor, $command): void {
            $now = $this->clock->now();
            $idempotency = $this->idempotency->claimIdempotency($command->submission, 'PROFILE_DUPLICATE_DISMISS', $this->fingerprints->generate('profile-duplicate-dismiss-idempotency', $actor->accountInternalId . "\0" . $command->casePublicId . "\0" . $command->expectedVersion . "\0" . hash('sha256', $command->reference . "\0" . $command->justification)), $now);
            if ($idempotency === IdempotencyClaimStatus::CONFLICT) {
                throw new \DomainException('Duplicate case is unavailable.');
            }
            $case = $this->repository->duplicateCase($command->casePublicId, true);
            if ($case === null || (int) $case['reported_by_account_id'] === $actor->accountInternalId) {
                throw new \DomainException('Duplicate case is unavailable.');
            }
            if ($idempotency === IdempotencyClaimStatus::REPLAY && $case['status'] === 'DISMISSED') {
                return;
            }
            if ((int) $case['version'] !== $command->expectedVersion || in_array($case['status'], ['DISMISSED', 'RESOLVED', 'BLOCKED'], true)) {
                throw new \DomainException('Duplicate case is unavailable.');
            }
            $this->stepUp->consumeWithGrant($actor, StepUpAction::PROFILE_DUPLICATE_DISMISS);
            $this->repository->transitionDuplicateCase($case, 'DISMISSED', 'NOT_DUPLICATE', null, $actor->accountInternalId, null, null, $command->reference, $command->justification, $now);
            $this->repository->recordDuplicateEvent((int) $case['id'], 'DISMISSED', $actor->accountInternalId, 'NOT_DUPLICATE', $now);
            $this->audit->platform(SecurityEventCode::PERSON_DUPLICATE_DISMISSED, SecurityEventSubjectKind::PERSON_DUPLICATE_CASE, (string) $case['public_id'], $actor->accountId->toString(), $now, ['duplicate_case_public_id' => $case['public_id']], 'NOT_DUPLICATE');
            $this->idempotency->completeIdempotency($command->submission, $now);
        }, TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
    }
}
