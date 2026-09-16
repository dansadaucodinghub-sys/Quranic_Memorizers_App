<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaProcessing\Application;

interface MediaScanner
{
    /** @return array{clean:bool,engine:string,safe_code:string} */
    public function scan(string $contents): array;
}
