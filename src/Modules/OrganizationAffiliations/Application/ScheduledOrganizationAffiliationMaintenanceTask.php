<?php

declare(strict_types=1);

namespace Qmdb\Modules\OrganizationAffiliations\Application;

use Qmdb\Shared\Background\Scheduler\ScheduledTaskExecutionContext;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskHandler;

final readonly class ScheduledOrganizationAffiliationMaintenanceTask implements ScheduledTaskHandler
{
    public function __construct(private OrganizationAffiliationService $service)
    {
    }
    public function __invoke(ScheduledTaskExecutionContext $context): mixed
    {
        $this->service->maintain();
        return null;
    }
}
