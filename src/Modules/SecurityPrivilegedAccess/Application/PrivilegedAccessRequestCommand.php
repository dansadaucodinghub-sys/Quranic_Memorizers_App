<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityPrivilegedAccess\Application;

use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationScopeType;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessDuration;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessJustification;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessReference;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessSubmissionId;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessType;
use Qmdb\Shared\Observability\Correlation\CorrelationId;

final readonly class PrivilegedAccessRequestCommand
{
    /** @param non-empty-list<PermissionCode> $permissions */
    public function __construct(
        public AuthenticatedAccountContext $actor,
        public PrivilegedAccessType $type,
        public AuthorizationScopeType $scope,
        public ?int $workspaceInternalId,
        public ?int $subjectMembershipInternalId,
        public array $permissions,
        public PrivilegedAccessDuration $duration,
        public PrivilegedAccessJustification $justification,
        public ?PrivilegedAccessReference $reference,
        public string $locale,
        public PrivilegedAccessSubmissionId $submissionId,
        public CorrelationId $correlationId,
    ) {
        if (!in_array($locale, ['en', 'ar'], true)) {
            throw new \InvalidArgumentException('Privileged-access request locale is invalid.');
        }
        if ($workspaceInternalId !== null && $workspaceInternalId < 1) {
            throw new \InvalidArgumentException('Privileged-access workspace is invalid.');
        }
        if ($subjectMembershipInternalId !== null && $subjectMembershipInternalId < 1) {
            throw new \InvalidArgumentException('Privileged-access membership is invalid.');
        }
    }
}
