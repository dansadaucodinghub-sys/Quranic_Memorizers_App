<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Application;

use Qmdb\Shared\Identifier\UuidV7;

/**
 * Read boundary for the private Qur'an release-governance interface.
 *
 * @phpstan-type ReleaseListItem array{public_id:string,release_code:string,release_version:string,status:string,version:int,created_at:string}
 * @phpstan-type ReleaseDetail array{public_id:string,release_code:string,release_version:string,status:string,version:int,created_at:string,updated_at:string}
 */
interface QuranReleaseGovernanceReadRepository
{
    /** @return list<ReleaseListItem> */
    public function listRecent(int $limit): array;

    /** @return ReleaseDetail|null */
    public function find(UuidV7 $publicId): ?array;
}
