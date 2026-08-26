<?php

declare(strict_types=1);

namespace Qmdb\Modules\Tenancy\Application;

use LogicException;
use Qmdb\Modules\Tenancy\Domain\Value\WorkspaceId;

final readonly class TenantContext
{
    private function __construct(private ?int $workspaceInternalId, private ?WorkspaceId $workspaceId)
    {
    }

    public static function trusted(int $workspaceInternalId, WorkspaceId $workspaceId): self
    {
        if ($workspaceInternalId < 1) {
            throw new LogicException('Trusted tenant context requires a positive internal workspace ID.');
        }

        return new self($workspaceInternalId, $workspaceId);
    }

    public static function platform(): self
    {
        return new self(null, null);
    }

    public function isSystem(): bool
    {
        return $this->workspaceInternalId === null;
    }

    public function workspaceInternalId(): int
    {
        if ($this->workspaceInternalId === null) {
            throw new LogicException('System context has no workspace.');
        }

        return $this->workspaceInternalId;
    }

    public function workspaceId(): WorkspaceId
    {
        if ($this->workspaceId === null) {
            throw new LogicException('System context has no workspace.');
        }

        return $this->workspaceId;
    }
}
