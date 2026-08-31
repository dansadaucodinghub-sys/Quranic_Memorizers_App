<?php

declare(strict_types=1);

namespace Qmdb\Modules\Geography\Domain\Repository;

interface GeographyDatasetRepository
{
    /** @return array{public_id:string,dataset_code:string,dataset_version:string,content_sha256:string,record_count:int}|null */
    public function findActiveForCountry(string $isoAlpha2): ?array;
}
