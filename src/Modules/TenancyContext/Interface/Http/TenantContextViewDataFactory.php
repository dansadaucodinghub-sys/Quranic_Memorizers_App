<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Interface\Http;

use Qmdb\Modules\TenancyContext\Application\AccountWorkspaceInventory;
use Qmdb\Shared\Presentation\View\ViewData;

final readonly class TenantContextViewDataFactory
{
    public static function inventory(
        AccountWorkspaceInventory $inventory,
        string $switchCsrf,
        string $clearCsrf,
    ): ViewData {
        $current = $inventory->current;

        return new ViewData([
            'tenant_context_version' => $inventory->version->value,
            'current_workspace_id' => $current === null ? '' : $current->workspaceId->toString(),
            'current_workspace_name' => $current === null ? '' : $current->workspaceName,
            'switch_csrf_token' => $switchCsrf,
            'clear_csrf_token' => $clearCsrf,
            'next_cursor' => $inventory->nextCursor ?? '',
            'workspaces' => array_map(static fn ($workspace): array => [
                'id' => $workspace->workspaceId,
                'name' => $workspace->workspaceName,
                'selected' => $workspace->current,
            ], $inventory->items),
        ]);
    }
}
