<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionLive\Application;

/** Public reads intentionally expose only an already-public projection snapshot. */
interface CompetitionLivePublicReadRepository
{
    /** @return array{sequence:int,payload:array<string,mixed>,checksum:string}|null */
    public function currentSnapshot(string $editionSlug): ?array;

    /**
     * Returns only public-safe immutable snapshots after a trusted SSE cursor.
     * A bounded response lets a reconnecting browser recover without making a
     * projection stream an authority for operational state.
     *
     * @return list<array{sequence:int,payload:array<string,mixed>,checksum:string}>
     */
    public function snapshotsAfter(string $editionSlug, int $afterSequence, int $limit): array;
}
