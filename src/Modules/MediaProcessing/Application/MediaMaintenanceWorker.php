<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaProcessing\Application;

interface MediaMaintenanceWorker
{
    /** @return array{examined:int,changed:int,dry_run:bool} */
    public function run(string $operation, bool $dryRun = false): array;
}
