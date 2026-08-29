<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace Qmdb\Modules\SecurityPrivilegedAccess\Domain;

use LogicException;
use Qmdb\Modules\Tenancy\Application\TenantContext;
use Qmdb\Modules\Tenancy\Domain\Value\WorkspaceId;
use Qmdb\Shared\Presentation\View\TenantPresentationContext;

/** A workspace scope for exceptional access. It deliberately carries no membership or role identity. */
final readonly class PrivilegedWorkspaceTenantContext implements TenantPresentationContext
{
    public function __construct(
        public PrivilegedAccessContext $access,
        public WorkspaceId $workspaceId,
        public string $workspaceName,
    ) {
        if ($access->workspaceInternalId === null || trim($workspaceName) === '') {
            throw new \InvalidArgumentException('Privileged workspace tenant context is invalid.');
        }
    }

    public function tenant(): TenantContext
    {
        return TenantContext::trusted($this->access->workspaceInternalId ?? throw new LogicException('Workspace is unavailable.'), $this->workspaceId);
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
        return $this->access->tenantContextVersion;
    }

    public function jsonSerialize(): never
    {
        throw new LogicException('Privileged workspace tenant context cannot be serialized.');
    }

    /** @return array<string, int|string> */
    public function __debugInfo(): array
    {
        return [
            'workspace_public_id' => $this->workspaceId->toString(),
            'workspace_name' => $this->workspaceName,
            'access_type' => $this->access->type->value,
            'tenant_context_version' => $this->access->tenantContextVersion,
            'subject' => '[redacted]',
        ];
    }
}
