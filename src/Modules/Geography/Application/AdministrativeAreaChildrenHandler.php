<?php

declare(strict_types=1);

namespace Qmdb\Modules\Geography\Application;

use Qmdb\Modules\Geography\Domain\Repository\AdministrativeAreaRepository;

final readonly class AdministrativeAreaChildrenHandler
{
    public function __construct(private AdministrativeAreaRepository $areas)
    {
    }

    /** @return list<array{public_id:string,canonical_code:string,canonical_slug:string,official_name:string,area_type:string,administrative_level:int}> */
    public function handle(AdministrativeAreaChildrenQuery $query): array
    {
        return $this->areas->listActiveChildren($query->parentId->value());
    }
}
