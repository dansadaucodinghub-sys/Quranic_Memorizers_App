<?php

declare(strict_types=1);

namespace Qmdb\Modules\Geography\Domain\Repository;

interface AdministrativeAreaRepository
{
    /** @return list<array{public_id:string,canonical_code:string,canonical_slug:string,official_name:string,area_type:string,administrative_level:int}> */
    public function listActiveLevelOne(string $countryIsoAlpha2): array;

    /** @return array{public_id:string,canonical_code:string,canonical_slug:string,official_name:string,area_type:string,administrative_level:int}|null */
    public function findActiveLevelOneBySlug(string $countryIsoAlpha2, string $slug): ?array;

    /** @return array{public_id:string,canonical_code:string,canonical_slug:string,official_name:string,area_type:string,administrative_level:int}|null */
    public function findActiveByPublicId(string $publicId): ?array;

    /** @return list<array{public_id:string,canonical_code:string,canonical_slug:string,official_name:string,area_type:string,administrative_level:int}> */
    public function listActiveChildren(string $parentPublicId, int $limit = 60): array;

    /** @return list<array{public_id:string,canonical_code:string,canonical_slug:string,official_name:string,area_type:string,administrative_level:int,parent_slug:?string}> */
    public function searchActiveNigeria(string $query, int $limit = 30): array;
}
