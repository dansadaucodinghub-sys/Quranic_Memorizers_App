<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Domain;

use JsonSerializable;
use LogicException;
use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\IdentitySessions\Domain\SessionId;
use Qmdb\Modules\Tenancy\Application\TenantContext;
use Qmdb\Modules\Tenancy\Domain\MembershipStatus;
use Qmdb\Modules\Tenancy\Domain\Value\WorkspaceId;
use Qmdb\Modules\Tenancy\Domain\WorkspaceStatus;
use Qmdb\Shared\Presentation\View\TenantPresentationContext;
use Qmdb\Shared\Time\UtcDateTime;

final readonly class AccountWorkspaceTenantContext implements JsonSerializable, TenantPresentationContext
{
    private function __construct(
        public int $accountInternalId,
        public AccountId $accountId,
        public int $sessionInternalId,
        public SessionId $sessionId,
        public int $workspaceInternalId,
        public WorkspaceId $workspaceId,
        public WorkspaceStatus $workspaceStatus,
        public int $workspaceVersion,
        public ResolvedWorkspaceMembershipIdentity $membership,
        public string $workspaceName,
        public TenantContextVersion $version,
        \DateTimeImmutable $selectedAt,
    ) {
        if (
            $accountInternalId < 1 || $sessionInternalId < 1 || $workspaceInternalId < 1
            || $workspaceVersion < 1 || trim($workspaceName) === ''
            || $workspaceStatus !== WorkspaceStatus::ACTIVE
            || $membership->accountInternalId !== $accountInternalId
            || $membership->workspaceInternalId !== $workspaceInternalId
            || $membership->status !== MembershipStatus::ACTIVE
        ) {
            throw new \InvalidArgumentException('Account workspace tenant context is invalid.');
        }
        $this->selectedAt = UtcDateTime::normalize($selectedAt);
    }

    public static function trusted(
        int $accountInternalId,
        AccountId $accountId,
        int $sessionInternalId,
        SessionId $sessionId,
        int $workspaceInternalId,
        WorkspaceId $workspaceId,
        WorkspaceStatus $workspaceStatus,
        int $workspaceVersion,
        ResolvedWorkspaceMembershipIdentity $membership,
        string $workspaceName,
        TenantContextVersion $version,
        \DateTimeImmutable $selectedAt,
    ): self {
        return new self(
            $accountInternalId,
            $accountId,
            $sessionInternalId,
            $sessionId,
            $workspaceInternalId,
            $workspaceId,
            $workspaceStatus,
            $workspaceVersion,
            $membership,
            $workspaceName,
            $version,
            $selectedAt,
        );
    }

    public \DateTimeImmutable $selectedAt;

    public function membershipInternalId(): int
    {
        return $this->membership->membershipInternalId;
    }

    public function tenant(): TenantContext
    {
        return TenantContext::trusted($this->workspaceInternalId, $this->workspaceId);
    }

    public function workspacePublicId(): string
    {
        return $this->workspaceId->toString();
    }

    public function workspaceDisplayName(): string
    {
        return $this->workspaceName;
    }

    public function tenantContextVersion(): int
    {
        return $this->version->value;
    }

    public function equals(self $other): bool
    {
        return $this->accountInternalId === $other->accountInternalId
            && $this->accountId->toString() === $other->accountId->toString()
            && $this->sessionInternalId === $other->sessionInternalId
            && $this->sessionId->toString() === $other->sessionId->toString()
            && $this->workspaceInternalId === $other->workspaceInternalId
            && $this->workspaceId->toString() === $other->workspaceId->toString()
            && $this->workspaceStatus === $other->workspaceStatus
            && $this->workspaceVersion === $other->workspaceVersion
            && $this->workspaceName === $other->workspaceName
            && $this->membership->equals($other->membership)
            && $this->version->value === $other->version->value
            && $this->selectedAt == $other->selectedAt;
    }

    public function jsonSerialize(): never
    {
        throw new LogicException('Account workspace tenant context cannot be serialized.');
    }

    /** @return array<never, never> */
    public function __serialize(): array
    {
        throw new LogicException('Account workspace tenant context cannot be serialized.');
    }

    /** @return array<string, int|string> */
    public function __debugInfo(): array
    {
        return [
            'account_id' => '[redacted]',
            'session_id' => '[redacted]',
            'workspace_public_id' => $this->workspaceId->toString(),
            'workspace_status' => $this->workspaceStatus->value,
            'workspace_version' => $this->workspaceVersion,
            'membership_version' => $this->membership->version,
            'tenant_context_version' => $this->version->value,
            'selected_at' => $this->selectedAt->format(DATE_ATOM),
        ];
    }
}
