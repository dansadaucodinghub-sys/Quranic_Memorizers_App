<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Application;

use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\IdentityAccess\Domain\IdempotencyClaimStatus;
use Qmdb\Modules\IdentityAccess\Domain\Repository\IdentityAccessRepository;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityResolution\Domain\IdentityResolutionSubmissionId;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequest;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequirementGuard;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationSubject;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\PlatformAuthorizationScope;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Database\Transaction\TransactionOptions;
use Qmdb\Shared\Database\Transaction\TransactionRetryPolicy;
use Qmdb\Shared\Time\Clock;

/** Starts an explicit human review; it never selects a canonical Person. */
final readonly class PersonDuplicateReviewService
{
    public function __construct(
        private \Qmdb\Modules\IdentityResolution\Infrastructure\Persistence\MySqlIdentityResolutionRepository $repository,
        private IdentityAccessRepository $idempotency,
        private IdentityFingerprintGenerator $fingerprints,
        private AuthorizationRequirementGuard $authorization,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {
    }

    public function start(AuthenticatedAccountContext $actor, string $casePublicId, int $expectedVersion, IdentityResolutionSubmissionId $submission): void
    {
        $this->authorization->requireAllowed(new AuthorizationRequest(
            AuthorizationSubject::fromAuthenticatedContext($actor),
            new PermissionCode('platform.people_duplicates.view'),
            new PlatformAuthorizationScope(),
        ));
        $this->transactions->transactional(function () use ($actor, $casePublicId, $expectedVersion, $submission): void {
            $case = $this->repository->duplicateCase($casePublicId, true);
            if ($case === null || (int) $case['reported_by_account_id'] === $actor->accountInternalId) {
                throw new \DomainException('Duplicate case is unavailable.');
            }
            foreach (array_merge($this->repository->managementAuthorities((int) $case['first_person_id']), $this->repository->managementAuthorities((int) $case['second_person_id'])) as $authority) {
                if ($authority['account_id'] === $actor->accountInternalId) {
                    throw new \DomainException('Duplicate case is unavailable.');
                }
            }
            $idempotency = $this->idempotency->claimIdempotency(
                $submission,
                'PROFILE_DUPLICATE_REVIEW',
                $this->fingerprints->generate('profile-duplicate-review-idempotency', $actor->accountInternalId . "\0" . $casePublicId . "\0" . $expectedVersion),
                $this->clock->now(),
            );
            if ($idempotency === IdempotencyClaimStatus::CONFLICT) {
                throw new \DomainException('Duplicate case is unavailable.');
            }
            if ($idempotency === IdempotencyClaimStatus::REPLAY) {
                if ($case['status'] !== 'UNDER_REVIEW') {
                    throw new \DomainException('Duplicate case replay is unavailable.');
                }

                return;
            }
            if ($case['status'] === 'UNDER_REVIEW') {
                throw new \DomainException('Duplicate case is unavailable.');
            }
            if ($case['status'] !== 'READY_FOR_REVIEW' || (int) $case['version'] !== $expectedVersion) {
                throw new \DomainException('Duplicate case is unavailable.');
            }
            $this->repository->transitionDuplicateCase($case, 'UNDER_REVIEW', null, null, $actor->accountInternalId, null, null, null, null, $this->clock->now());
            $this->repository->recordDuplicateEvent((int) $case['id'], 'REVIEW_STARTED', $actor->accountInternalId, 'DUPLICATE_REVIEW_STARTED', $this->clock->now());
            $this->idempotency->completeIdempotency($submission, $this->clock->now());
        }, TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
    }
}
