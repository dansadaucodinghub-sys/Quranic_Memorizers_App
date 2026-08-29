<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Application;

final readonly class AccountWorkspaceInventoryItem
{
    public function __construct(
        public string $workspaceId,
        public string $workspaceName,
        public string $workspaceStatus,
        public string $membershipId,
        public string $membershipStatus,
        public bool $current,
        public \DateTimeImmutable $workspaceUpdatedAt,
    ) {
        if ($workspaceId === '' || trim($workspaceName) === '' || $membershipId === '') {
            throw new \InvalidArgumentException('Account workspace inventory item is invalid.');
        }
    }
}
