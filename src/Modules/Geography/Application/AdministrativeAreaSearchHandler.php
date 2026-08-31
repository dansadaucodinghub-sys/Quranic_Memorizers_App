<?php

declare(strict_types=1);

namespace Qmdb\Modules\Geography\Application;

use Qmdb\Modules\Geography\Domain\Repository\AdministrativeAreaRepository;

final readonly class AdministrativeAreaSearchHandler
{
    public function __construct(private AdministrativeAreaRepository $areas)
    {
    }

    /** @return list<array{public_id:string,canonical_code:string,canonical_slug:string,official_name:string,area_type:string,administrative_level:int,parent_slug:?string}> */
    public function handle(AdministrativeAreaSearchQuery $query): array
    {
        return $this->areas->searchActiveNigeria($query->query);
    }
}
