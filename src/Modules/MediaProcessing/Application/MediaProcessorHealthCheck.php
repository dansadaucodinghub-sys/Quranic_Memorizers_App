<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaProcessing\Application;

interface MediaProcessorHealthCheck
{
    /** @return array{healthy:bool,engine:string,safe_code:string} */
    public function check(): array;
}
