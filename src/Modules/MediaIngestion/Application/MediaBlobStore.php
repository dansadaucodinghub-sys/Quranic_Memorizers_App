<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaIngestion\Application;

/** Private binary storage; callers receive opaque object keys only. */
interface MediaBlobStore
{
    public function putImmutable(string $objectKey, string $contents): void;
    public function get(string $objectKey): string;
}
