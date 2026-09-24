<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Application;

use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitAttempt;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitPolicy;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitScope;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequest;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequirementGuard;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationSubject;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\WorkspaceAuthorizationScope;
use Qmdb\Modules\TenancyContext\Domain\AccountWorkspaceTenantContext;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Time\Clock;

final readonly class CommunityProfileService
{
    public function __construct(
        private CommunityProfileRepository $repository,
        private AuthorizationRequirementGuard $authorization,
        private TransactionManager $transactions,
        private Clock $clock,
        private CommunityGlobalReceipts $receipts,
        private IdentityRateLimiter $rateLimits,
        private IdentityFingerprintGenerator $fingerprints,
    ) {
    }

    /** @return array{public_id:string,alias:string,visibility:string,version:int}|null */
    public function mine(AuthenticatedAccountContext $actor): ?array
    {
        return $this->transactions->transactional(
            fn (): ?array => $this->repository->mine($actor->accountInternalId)
        );
    }

    /** @return array{public_id:string,status:string,version:int} */
    public function save(
        AuthenticatedAccountContext $actor,
        AccountWorkspaceTenantContext $tenant,
        UuidV7 $submission,
        ?UuidV7 $profileId,
        int $expectedVersion,
        string $alias,
        string $visibility
    ): array {
        $this->requireCreator($actor, $tenant);
        if (
            !$this->validAlias($alias) || !in_array($visibility, ['PRIVATE', 'PUBLIC'], true)
            || $expectedVersion < 0 || ($profileId === null) !== ($expectedVersion === 0)
        ) {
            throw new \InvalidArgumentException('Community profile form is invalid.');
        }
        if ($profileId === null && $visibility !== 'PRIVATE') {
            throw new \DomainException('New community profiles must be private.');
        }
        $operation = $profileId === null ? 'PROFILE_CREATE' : 'PROFILE_UPDATE';
        $fingerprint = hash('sha256', json_encode([$actor->accountInternalId,
            $tenant->workspaceInternalId, $operation, $profileId?->toString(),
            $expectedVersion, $alias, $visibility], JSON_THROW_ON_ERROR), true);
        $completed = $this->transactions->transactional(fn (): ?array => $this->receipts->completedForActor(
            $submission,
            $actor->accountInternalId,
            $operation,
            $fingerprint
        ));
        if ($completed !== null) {
            return $completed;
        }
        $limit = $this->rateLimits->consume([new IdentityRateLimitAttempt(
            IdentityRateLimitScope::COMMUNITY_MUTATION_ACCOUNT,
            $this->fingerprints->generate('community-profile-account', (string) $actor->accountInternalId),
            new IdentityRateLimitPolicy(3600, 12, 3600)
        )], $this->clock->now());
        if (!$limit->allowed) {
            throw new \DomainException('Community profile rate limit exceeded.');
        }
        return $this->transactions->transactional(function () use (
            $actor,
            $submission,
            $profileId,
            $expectedVersion,
            $alias,
            $visibility,
            $operation,
            $fingerprint
        ): array {
            $now = $this->clock->now();
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
            $result = $profileId === null
                ? $this->repository->createPrivate($actor->accountInternalId, $alias, $now)
                : $this->repository->updateOwn(
                    $actor->accountInternalId,
                    $profileId,
                    $expectedVersion,
                    $alias,
                    $visibility,
                    $now
                );
            $this->receipts->complete(
                $submission,
                UuidV7::fromString($result['public_id']),
                $result['visibility'],
                $result['version'],
                $now
            );
            return ['public_id' => $result['public_id'], 'status' => $result['visibility'],
                'version' => $result['version']];
        });
    }

    /** @return array{public_id:string,visibility:string,version:int} */
    public function createPrivate(
        AuthenticatedAccountContext $actor,
        AccountWorkspaceTenantContext $tenant,
        string $alias
    ): array {
        $this->requireCreator($actor, $tenant);
        if (!$this->validAlias($alias)) {
            throw new \InvalidArgumentException('Profile alias must be plain text of 1–80 characters.');
        }
        return $this->transactions->transactional(fn (): array => $this->repository->createPrivate(
            $actor->accountInternalId,
            $alias,
            $this->clock->now(),
        ));
    }

    /** @return array{public_id:string,visibility:string,version:int} */
    public function changeVisibility(
        AuthenticatedAccountContext $actor,
        AccountWorkspaceTenantContext $tenant,
        UuidV7 $profileId,
        int $expectedVersion,
        string $visibility
    ): array {
        $this->requireCreator($actor, $tenant);
        if ($expectedVersion < 1 || !in_array($visibility, ['PRIVATE', 'PUBLIC'], true)) {
            throw new \InvalidArgumentException('Profile visibility or version is invalid.');
        }
        return $this->transactions->transactional(fn (): array => $this->repository->changeVisibility(
            $actor->accountInternalId,
            $profileId,
            $expectedVersion,
            $visibility,
            $this->clock->now(),
        ));
    }

    private function validAlias(string $alias): bool
    {
        return mb_strlen($alias) >= 1 && mb_strlen($alias) <= 80
            && !str_contains($alias, "\0") && strip_tags($alias) === $alias
            && preg_match('/[\p{L}\p{N}]/u', $alias) === 1;
    }

    private function requireCreator(AuthenticatedAccountContext $actor, AccountWorkspaceTenantContext $tenant): void
    {
        if (
            $actor->accountInternalId !== $tenant->accountInternalId
            || $actor->sessionInternalId !== $tenant->sessionInternalId
        ) {
            throw new \DomainException('Profile context does not match the authenticated session.');
        }
        $this->authorization->requireAllowed(new AuthorizationRequest(
            AuthorizationSubject::fromAuthenticatedContext($actor),
            new PermissionCode('community.clips.create'),
            new WorkspaceAuthorizationScope($tenant),
        ));
    }
}
