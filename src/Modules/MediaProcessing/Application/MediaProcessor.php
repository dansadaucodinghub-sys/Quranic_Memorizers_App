<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaProcessing\Application;

interface MediaProcessor
{
    /** @param array{profile:string,input_mime:string,output_mime:string} $profile */
    public function process(string $contents, array $profile): string;
}
