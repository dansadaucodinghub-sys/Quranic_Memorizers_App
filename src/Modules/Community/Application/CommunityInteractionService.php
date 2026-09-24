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

/** Public authority is rechecked before every Clip interaction, including removal. */
final readonly class CommunityInteractionService
{
    public function __construct(
        private CommunityPublicClipReader $publicClips,
        private CommunityInteractionRepository $repository,
        private CommunityGlobalReceipts $receipts,
        private IdentityRateLimiter $rateLimits,
        private IdentityFingerprintGenerator $fingerprints,
        private TransactionManager $transactions,
        private Clock $clock,
        private CommunityParticipantEligibility $eligibility,
        private CommunityPublicClipService $clips,
    ) {
    }

    /** @return list<array{clip_id:string,profile_id:string,alias:string,caption:string,language:string,surah:int,start:int,end:int,published_at:string,media_kind:string,comment_policy:string,media_url:string}> */
    public function bookmarks(AuthenticatedAccountContext $actor): array
    {
        $ids = $this->transactions->transactional(fn (): array => $this->repository->bookmarkedClipIds(
            $actor->accountInternalId
        ));
        $result = [];
        foreach ($ids as $id) {
            $clip = $this->clips->detail($id, $actor->accountInternalId);
            if ($clip !== null) {
                $result[] = $clip;
            }
        }
        return $result;
    }

    /** @return array{reaction:array{active:bool,version:int},bookmark:array{active:bool,version:int}}|null */
    public function state(AuthenticatedAccountContext $actor, UuidV7 $clipId): ?array
    {
        return $this->transactions->transactional(function () use ($actor, $clipId): ?array {
            $visible = $this->publicClips->find($clipId, $actor->accountInternalId);
            return $visible === null ? null : $this->repository->state(
                $visible['workspace_id'],
                $clipId,
                $actor->accountInternalId
            );
        });
    }

    /** @return array{public_id:string,status:string,version:int} */
    public function transition(
        AuthenticatedAccountContext $actor,
        UuidV7 $submission,
        UuidV7 $clipId,
        string $kind,
        string $action,
        int $expectedVersion
    ): array {
        if (
            !in_array($kind, ['REACTION', 'BOOKMARK'], true)
            || !in_array($action, ['ADD', 'REMOVE'], true) || $expectedVersion < 0
        ) {
            throw new \InvalidArgumentException('Clip interaction is invalid.');
        }
        $operation = ($kind === 'REACTION' ? 'REACT_' : 'BOOKMARK_') . $action;
        $fingerprint = hash('sha256', json_encode([$actor->accountInternalId,
            $clipId->toString(), $kind, $action, $expectedVersion], JSON_THROW_ON_ERROR), true);
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
            $this->fingerprints->generate('community-interaction-account', (string) $actor->accountInternalId),
            new IdentityRateLimitPolicy(3600, 120, 3600),
        )], $now);
        if (!$limit->allowed) {
            throw new \DomainException('Clip interaction rate limit exceeded.');
        }
        return $this->transactions->transactional(function () use (
            $actor,
            $submission,
            $clipId,
            $kind,
            $action,
            $expectedVersion,
            $operation,
            $fingerprint,
            $now
): array {
            if ($action === 'ADD') {
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
            $result = $this->repository->transition(
                $visible['workspace_id'],
                $clipId,
                $actor->accountInternalId,
                $kind,
                $action,
                $expectedVersion,
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
}
