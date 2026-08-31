<?php

declare(strict_types=1);

namespace Qmdb\Modules\Geography\Domain\Repository;

/** @phpstan-type GeographyCountryView array{public_id:string,iso_alpha2:string,iso_alpha3:string,iso_numeric:string,common_name:string,official_name:string,canonical_slug:string} */
interface CountryRepository
{
    /** @return GeographyCountryView|null */
    public function findActiveByIsoAlpha2(string $code): ?array;
}
