<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaIngestion\Application;

/** Only a locked, expired upload-part record can authorize this operation. */
interface MediaStagingCleaner
{
    public function removeStaging(string $objectKey, string $expectedSha256): bool;
}
