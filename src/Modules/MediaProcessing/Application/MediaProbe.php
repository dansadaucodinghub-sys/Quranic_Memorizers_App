<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaProcessing\Application;

interface MediaProbe
{
    /** @return array{mime:string,duration_ms:int,width:?int,height:?int,codec:string} */
    public function inspect(string $privateObjectKey): array;
}
