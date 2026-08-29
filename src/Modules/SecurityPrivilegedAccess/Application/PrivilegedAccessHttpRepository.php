<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityPrivilegedAccess\Application;

use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessRequestId;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessReviewId;

interface PrivilegedAccessHttpRepository
{
    /** @return list<array{id: string, type: string, scope: string, workspace_name: string, status: string, requested_duration: int, approved_duration: ?int, request_expires_at: string, review_status: ?string}> */
    public function ownRequests(AuthenticatedAccountContext $actor, int $limit = 50): array;

    public function activeWorkspaceInternalId(string $workspacePublicId): ?int;

    public function reviewForRequest(PrivilegedAccessRequestId $requestId): ?PrivilegedAccessReviewId;
}
