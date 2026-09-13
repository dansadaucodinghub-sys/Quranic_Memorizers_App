<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionLive\Application;

/** Public reads intentionally expose only an already-public projection snapshot. */
interface CompetitionLivePublicReadRepository
{
    /** @return array{sequence:int,payload:array<string,mixed>,checksum:string}|null */
    public function currentSnapshot(string $editionSlug): ?array;
}
