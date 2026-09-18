<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaProcessing\Application;

interface MediaRuntimeReadiness
{
    /** @return array{healthy:bool,checks:array<string,string>} */
    public function check(): array;
}
