<?php

declare(strict_types=1);

namespace Qmdb\Modules\PilotOfflineRollout\Application;

interface OfflineOperationHandler
{
    public function operation(): string;

    public function owningDomain(): string;

    public function apply(OfflineOperationContext $context): OfflineOperationResult;
}
