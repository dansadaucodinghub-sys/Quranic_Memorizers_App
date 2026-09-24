<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Application;

use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitAttempt;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitPolicy;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitScope;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Time\Clock;

final readonly class CommunityCommentService
{
    public function __construct(
        private CommunityPublicClipReader $publicClips,
        private CommunityCommentRepository $comments,
        private CommunityGlobalReceipts $receipts,
        private IdentityRateLimiter $rateLimits,
        private IdentityFingerprintGenerator $fingerprints,
        private TransactionManager $transactions,
        private Clock $clock,
        private CommunityParticipantEligibility $eligibility,
    ) {
    }

    /** @return list<array{public_id:string,parent_id:?string,body:string,status:string,version:int,created_at:string,is_mine:bool}>|null */
    public function visible(UuidV7 $clipId, ?int $viewerAccountId): ?array
    {
        return $this->transactions->transactional(function () use ($clipId, $viewerAccountId): ?array {
            $visible = $this->publicClips->find($clipId, $viewerAccountId);
            return $visible === null ? null : $this->comments->visible(
                $visible['workspace_id'],
                $clipId,
                $viewerAccountId
            );
        });
    }

    /** @return array{public_id:string,status:string,version:int} */
    public function create(
        AuthenticatedAccountContext $actor,
        UuidV7 $submission,
        UuidV7 $clipId,
        ?UuidV7 $parentId,
        string $body
    ): array {
        $this->validateBody($body);
        return $this->execute(
            $actor,
            $submission,
            $clipId,
            $parentId,
            null,
            $parentId === null ? 'COMMENT_CREATE' : 'REPLY_CREATE',
            0,
            $body
        );
    }

    /** @return array{public_id:string,status:string,version:int} */
    public function edit(
        AuthenticatedAccountContext $actor,
        UuidV7 $submission,
        UuidV7 $clipId,
        UuidV7 $commentId,
        int $expectedVersion,
        string $body
    ): array {
        $this->validateBody($body);
        return $this->execute(
            $actor,
            $submission,
            $clipId,
            null,
            $commentId,
            'COMMENT_EDIT',
            $expectedVersion,
            $body
        );
    }

    /** @return array{public_id:string,status:string,version:int} */
    public function remove(
        AuthenticatedAccountContext $actor,
        UuidV7 $submission,
        UuidV7 $clipId,
        UuidV7 $commentId,
        int $expectedVersion
    ): array {
        return $this->execute(
            $actor,
            $submission,
            $clipId,
            null,
            $commentId,
            'COMMENT_REMOVE',
            $expectedVersion,
            ''
        );
    }

    /** @return array{public_id:string,status:string,version:int} */
    private function execute(
        AuthenticatedAccountContext $actor,
        UuidV7 $submission,
        UuidV7 $clipId,
        ?UuidV7 $parentId,
        ?UuidV7 $commentId,
        string $operation,
        int $expectedVersion,
        string $body
    ): array {
        if ($expectedVersion < 0 || ($commentId !== null && $expectedVersion < 1)) {
            throw new \InvalidArgumentException('Comment version is invalid.');
        }
        $fingerprint = hash('sha256', json_encode([$actor->accountInternalId,
            $clipId->toString(), $parentId?->toString(), $commentId?->toString(),
            $operation, $expectedVersion, $body], JSON_THROW_ON_ERROR), true);
        $completed = $this->transactions->transactional(fn (): ?array => $this->receipts->completedForActor(
            $submission,
            $actor->accountInternalId,
            $operation,
            $fingerprint
        ));
        if ($completed !== null) {
            return $completed;
        }
        $now = $this->clock->now();
        $limit = $this->rateLimits->consume([new IdentityRateLimitAttempt(
            IdentityRateLimitScope::COMMUNITY_MUTATION_ACCOUNT,
            $this->fingerprints->generate('community-comment-account', (string) $actor->accountInternalId),
            new IdentityRateLimitPolicy(3600, 30, 3600),
        )], $now);
        if (!$limit->allowed) {
            throw new \DomainException('Comment rate limit exceeded.');
        }
        return $this->transactions->transactional(function () use (
            $actor,
            $submission,
            $clipId,
            $parentId,
            $commentId,
            $operation,
            $expectedVersion,
            $body,
            $fingerprint,
            $now
): array {
            if ($operation !== 'COMMENT_REMOVE') {
                $this->eligibility->requireAdultSelfLinkedAccount($actor->accountInternalId);
            }
            $replay = $this->receipts->claim(
                $submission,
                $actor->accountInternalId,
                $operation,
                $fingerprint,
                $now
            );
            if ($replay !== null) {
                return $replay;
            }
            $visible = $this->publicClips->find($clipId, $actor->accountInternalId);
            if ($visible === null) {
                throw new \DomainException('Clip is unavailable.');
            }
            $result = $commentId === null
                ? $this->comments->create(
                    $visible['workspace_id'],
                    $clipId,
                    $actor->accountInternalId,
                    $parentId,
                    $body,
                    $now
                )
                : $this->comments->transition(
                    $visible['workspace_id'],
                    $clipId,
                    $commentId,
                    $actor->accountInternalId,
                    $operation === 'COMMENT_EDIT' ? 'EDIT' : 'REMOVE',
                    $expectedVersion,
                    $body,
                    $now
                );
            $this->receipts->complete(
                $submission,
                UuidV7::fromString($result['public_id']),
                $result['status'],
                $result['version'],
                $now
            );
            return $result;
        });
    }

    private function validateBody(string $body): void
    {
        if (
            mb_strlen($body) < 1 || mb_strlen($body) > 2000
            || str_contains($body, "\0") || strip_tags($body) !== $body
            || preg_match('//u', $body) !== 1
        ) {
            throw new \InvalidArgumentException('Comment body must be plain UTF-8 text of 1–2000 characters.');
        }
    }
}
