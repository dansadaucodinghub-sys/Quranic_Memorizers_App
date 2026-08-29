<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Application;

use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\TenancyContext\Domain\TenantContextVersion;
use Qmdb\Shared\Observability\Correlation\CorrelationId;

final readonly class WorkspaceContextClearingCommand
{
    public function __construct(
        public AuthenticatedAccountContext $account,
        public TenantContextVersion $expectedVersion,
        public ?CorrelationId $correlationId = null,
    ) {
    }
}
