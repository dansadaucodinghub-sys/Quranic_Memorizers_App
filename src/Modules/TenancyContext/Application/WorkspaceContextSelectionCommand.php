<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Application;

use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\Tenancy\Domain\Value\WorkspaceId;
use Qmdb\Modules\TenancyContext\Domain\TenantContextVersion;
use Qmdb\Shared\Observability\Correlation\CorrelationId;

final readonly class WorkspaceContextSelectionCommand
{
    public function __construct(
        public AuthenticatedAccountContext $account,
        public WorkspaceId $workspaceId,
        public TenantContextVersion $expectedVersion,
        public ?CorrelationId $correlationId = null,
    ) {
    }
}
