<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace Qmdb\Modules\SecurityPrivilegedAccess\Application;

use DateTimeImmutable;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessRequestId;

interface PrivilegedAccessLifecycleRepository
{
    public function requestSnapshot(PrivilegedAccessRequestId $requestId): ?PrivilegedAccessRequestSnapshot;

    public function hasOverdueReviewForSubject(int $subjectAccountInternalId, \Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessType $type): bool;

    public function approve(
        PrivilegedAccessApprovalCommand $command,
        int $stepUpGrantInternalId,
        DateTimeImmutable $now,
    ): PrivilegedAccessApprovalResult;

    public function cancel(
        AuthenticatedAccountContext $actor,
        PrivilegedAccessRequestId $requestId,
        string $correlationId,
        DateTimeImmutable $now,
    ): PrivilegedAccessRequestSnapshot;

    public function endActive(
        AuthenticatedAccountContext $actor,
        string $correlationId,
        DateTimeImmutable $now,
    ): ?PrivilegedAccessRequestSnapshot;

    public function revoke(
        AuthenticatedAccountContext $actor,
        PrivilegedAccessRequestId $requestId,
        string $correlationId,
        DateTimeImmutable $now,
    ): PrivilegedAccessRequestSnapshot;

    public function reviewSnapshot(\Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessReviewId $reviewId): ?PrivilegedAccessReviewSnapshot;

    public function completeReview(
        PrivilegedAccessReviewCommand $command,
        int $stepUpGrantInternalId,
        DateTimeImmutable $now,
    ): PrivilegedAccessReviewResult;
}
