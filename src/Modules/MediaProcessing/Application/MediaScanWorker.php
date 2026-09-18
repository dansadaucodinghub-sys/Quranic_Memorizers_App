<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaProcessing\Application;

interface MediaScanWorker
{
    /** @return array{claimed:bool,outcome:string} */
    public function processOne(): array;
}
