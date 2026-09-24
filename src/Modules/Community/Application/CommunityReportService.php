<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Application;

use Qmdb\Modules\Identity\Infrastructure\Security\ContactCipher;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitAttempt;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitPolicy;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitScope;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventSubjectKind;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Time\Clock;

/** Confidential, authenticated report intake. No report statement enters audit metadata. */
final readonly class CommunityReportService
{
    private const array REASONS = ['CHILD_SAFETY', 'HARASSMENT', 'HATE', 'PRIVACY',
        'RIGHTS', 'MISLEADING_REFERENCE', 'OTHER'];

    public function __construct(
        private CommunityReportRepository $reports,
        private CommunityOperationReceipts $receipts,
        private ContactCipher $cipher,
        private IdentityRateLimiter $rateLimits,
        private IdentityFingerprintGenerator $fingerprints,
        private SecurityAuditEventAppender $audit,
        private CommunityNotificationIntentRepository $notifications,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {
    }

    /** @return array{public_id:string,status:string,version:int} */
    public function submit(
        AuthenticatedAccountContext $actor,
        UuidV7 $submission,
        UuidV7 $clipId,
        string $reasonCode,
        string $statement
    ): array {
        if (
            !in_array($reasonCode, self::REASONS, true) || mb_strlen($statement) < 1
            || mb_strlen($statement) > 2000 || str_contains($statement, "\0")
        ) {
            throw new \InvalidArgumentException('Report reason or statement is invalid.');
        }
        $fingerprint = hash('sha256', json_encode([$actor->accountInternalId, $clipId->toString(),
            $reasonCode, $statement], JSON_THROW_ON_ERROR), true);
        $completed = $this->transactions->transactional(fn (): ?array => $this->receipts->completedForActor(
            $submission,
            $actor->accountInternalId,
            'REPORT_SUBMIT',
            $fingerprint
        ));
        if ($completed !== null) {
            return $completed;
        }
        $limit = $this->rateLimits->consume([new IdentityRateLimitAttempt(
            IdentityRateLimitScope::COMMUNITY_REPORT_ACCOUNT,
            $this->fingerprints->generate('community-report-account', (string) $actor->accountInternalId),
            new IdentityRateLimitPolicy(3600, 5, 3600),
        )], $this->clock->now());
        if (!$limit->allowed) {
            throw new \DomainException('Report submission rate limit exceeded.');
        }
        return $this->transactions->transactional(function () use (
            $actor,
            $submission,
            $clipId,
            $reasonCode,
            $statement,
            $fingerprint
): array {
            $now = $this->clock->now();
            $completed = $this->receipts->completedForActor(
                $submission,
                $actor->accountInternalId,
                'REPORT_SUBMIT',
                $fingerprint
            );
            if ($completed !== null) {
                return $completed;
            }
            $target = $this->reports->visibleTarget($clipId, $actor->accountInternalId);
            $replay = $this->receipts->claim(
                $submission,
                $target['workspace_id'],
                $actor->accountInternalId,
                'REPORT_SUBMIT',
                $fingerprint,
                $now
            );
            if ($replay !== null) {
                return $replay;
            }
            $result = $this->reports->submit(
                $target,
                $actor->accountInternalId,
                $reasonCode,
                $this->cipher->encrypt($statement),
                $this->cipher->keyId(),
                $now
            );
            $this->notifications->enqueue(
                $target['workspace_id'],
                $actor->accountInternalId,
                'REPORT_RECEIVED',
                UuidV7::fromString($result['public_id']),
                $result['version'],
                $result['status'],
                $now
            );
            $this->receipts->complete(
                $submission,
                UuidV7::fromString($result['public_id']),
                $result['status'],
                $result['version'],
                $now
            );
            $this->audit->workspace(
                SecurityEventCode::COMMUNITY_REPORT_SUBMITTED,
                $target['workspace_public_id'],
                SecurityEventSubjectKind::COMMUNITY_REPORT,
                $result['public_id'],
                $actor->accountId->toString(),
                $now,
                ['reason_code' => $reasonCode, 'operation_public_id' => $submission->toString()],
                $reasonCode
            );
            return $result;
        });
    }
}
