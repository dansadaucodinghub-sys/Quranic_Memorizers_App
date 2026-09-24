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

/** Account-scoped graph commands; receipts make retries exact, not implicit. */
final readonly class CommunitySocialService
{
    private const array ACTIONS = ['FOLLOW', 'UNFOLLOW', 'ACCEPT', 'DECLINE',
        'REVOKE_FOLLOWER', 'BLOCK', 'UNBLOCK', 'MUTE', 'UNMUTE'];

    public function __construct(
        private CommunitySocialRepository $repository,
        private CommunityGlobalReceipts $receipts,
        private IdentityRateLimiter $rateLimits,
        private IdentityFingerprintGenerator $fingerprints,
        private TransactionManager $transactions,
        private Clock $clock,
        private CommunityParticipantEligibility $eligibility,
    ) {
    }

    /** @return array{follow_status:string,follow_version:int,block_active:bool,block_version:int,mute_active:bool,mute_version:int,own_profile:bool} */
    public function state(AuthenticatedAccountContext $actor, UuidV7 $profileId): array
    {
        return $this->transactions->transactional(fn (): array => $this->repository->state(
            $actor->accountInternalId,
            $profileId
        ));
    }

    /** @return list<array{profile_id:string,alias:string,block_version:int,mute_version:int,blocked:bool,muted:bool}> */
    public function safetyList(AuthenticatedAccountContext $actor): array
    {
        return $this->transactions->transactional(fn (): array => $this->repository->safetyList(
            $actor->accountInternalId
        ));
    }

    /** @return list<array{profile_id:string,alias:string,status:string,version:int}> */
    public function incoming(AuthenticatedAccountContext $actor): array
    {
        return $this->transactions->transactional(fn (): array => $this->repository->incoming(
            $actor->accountInternalId
        ));
    }

    /** @return list<array{profile_id:string,alias:string,status:string,version:int}> */
    public function outgoing(AuthenticatedAccountContext $actor): array
    {
        return $this->transactions->transactional(fn (): array => $this->repository->outgoing(
            $actor->accountInternalId
        ));
    }

    /** @return array{public_id:string,status:string,version:int} */
    public function transition(
        AuthenticatedAccountContext $actor,
        UuidV7 $submission,
        UuidV7 $targetProfileId,
        string $action,
        int $expectedVersion
    ): array {
        if (!in_array($action, self::ACTIONS, true) || $expectedVersion < 0) {
            throw new \InvalidArgumentException('Social action or expected version is invalid.');
        }
        $fingerprint = hash('sha256', json_encode([
            $actor->accountInternalId, $targetProfileId->toString(), $action, $expectedVersion,
        ], JSON_THROW_ON_ERROR), true);
        $completed = $this->transactions->transactional(fn (): ?array => $this->receipts->completedForActor(
            $submission,
            $actor->accountInternalId,
            $action,
            $fingerprint
        ));
        if ($completed !== null) {
            return $completed;
        }
        $now = $this->clock->now();
        $limit = $this->rateLimits->consume([new IdentityRateLimitAttempt(
            IdentityRateLimitScope::COMMUNITY_MUTATION_ACCOUNT,
            $this->fingerprints->generate('community-mutation-account', (string) $actor->accountInternalId),
            new IdentityRateLimitPolicy(3600, 60, 3600),
        )], $now);
        if (!$limit->allowed) {
            throw new \DomainException('Community action rate limit exceeded.');
        }
        return $this->transactions->transactional(function () use (
            $actor,
            $submission,
            $targetProfileId,
            $action,
            $expectedVersion,
            $fingerprint,
            $now
): array {
            if ($action === 'FOLLOW') {
                $this->eligibility->requireAdultSelfLinkedAccount($actor->accountInternalId);
            }
            $replay = $this->receipts->claim(
                $submission,
                $actor->accountInternalId,
                $action,
                $fingerprint,
                $now
            );
            if ($replay !== null) {
                return $replay;
            }
            $result = $this->repository->transition(
                $actor->accountInternalId,
                $targetProfileId,
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
