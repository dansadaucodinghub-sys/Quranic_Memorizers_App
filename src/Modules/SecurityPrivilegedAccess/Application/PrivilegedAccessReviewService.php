<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace Qmdb\Modules\SecurityPrivilegedAccess\Application;

use Qmdb\Modules\IdentityMultiFactor\Application\StepUpGuard;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventSubjectKind;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequest;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationSubject;
use Qmdb\Modules\SecurityAuthorization\Application\BaseRoleAuthorizationGuard;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\PlatformAuthorizationScope;
use Qmdb\Modules\SecurityPrivilegedAccess\Configuration\PrivilegedAccessConfiguration;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessReviewStatus;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessType;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Time\Clock;

final readonly class PrivilegedAccessReviewService
{
    public function __construct(
        private BaseRoleAuthorizationGuard $authorization,
        private PrivilegedAccessLifecycleRepository $lifecycle,
        private StepUpGuard $stepUp,
        private PrivilegedAccessNotificationService $notifications,
        private PrivilegedAccessConfiguration $configuration,
        private SecurityAuditEventAppender $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {
    }

    public function complete(PrivilegedAccessReviewCommand $command): PrivilegedAccessReviewResult
    {
        if (strlen($command->summary) > $this->configuration->justificationMaximumBytes) {
            throw new \DomainException('The privileged-access review summary exceeds its maximum length.');
        }
        $review = $this->lifecycle->reviewSnapshot($command->reviewId);
        if (
            $review === null || !in_array($review->status, [PrivilegedAccessReviewStatus::PENDING, PrivilegedAccessReviewStatus::OVERDUE], true)
            || $review->subjectAccountInternalId === $command->actor->accountInternalId
        ) {
            throw new \DomainException('The privileged-access review is not available.');
        }
        $permission = $review->type === PrivilegedAccessType::SUPPORT_ACCESS
            ? 'platform.support_access.review' : 'platform.break_glass.review';
        $action = $review->type === PrivilegedAccessType::SUPPORT_ACCESS
            ? StepUpAction::SUPPORT_ACCESS_REVIEW : StepUpAction::BREAK_GLASS_REVIEW;
        $request = new AuthorizationRequest(
            AuthorizationSubject::fromAuthenticatedContext($command->actor),
            new PermissionCode($permission),
            new PlatformAuthorizationScope(),
        );
        $this->authorization->requireAllowed($request);

        return $this->transactions->transactional(function () use ($command, $request, $action, $review): PrivilegedAccessReviewResult {
            $this->authorization->requireAllowed($request);
            $grant = $this->stepUp->consumeWithGrant($command->actor, $action);
            $result = $this->lifecycle->completeReview($command, $grant->internalId, $this->clock->now());
            $this->notifications->create(
                $review->subjectAccountInternalId,
                $review->type === PrivilegedAccessType::SUPPORT_ACCESS
                    ? AccountSecurityNotificationType::SUPPORT_ACCESS_REVIEW_COMPLETED
                    : AccountSecurityNotificationType::BREAK_GLASS_REVIEW_COMPLETED,
                $result->reviewId->toString(),
                $this->clock->now(),
            );
            $now = $this->clock->now();
            $this->audit->privilegedAccess(
                $review->type === PrivilegedAccessType::SUPPORT_ACCESS
                    ? SecurityEventCode::SUPPORT_REVIEW_COMPLETED : SecurityEventCode::BREAK_GLASS_REVIEW_COMPLETED,
                $review->scope->value === 'WORKSPACE',
                $review->workspacePublicId,
                SecurityEventSubjectKind::PRIVILEGED_ACCESS,
                $result->reviewId->toString(),
                $command->actor->accountId->toString(),
                $now,
                ['access_type' => $review->type->value],
                null,
                $command->correlationId->value(),
            );

            return $result;
        });
    }
}
