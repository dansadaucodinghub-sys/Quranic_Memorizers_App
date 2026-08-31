<?php

declare(strict_types=1);

namespace Qmdb\Modules\Geography\Application;

use Qmdb\Modules\Geography\Domain\GeographyPublicId;

final readonly class AdministrativeAreaChildrenQuery
{
    public function __construct(public GeographyPublicId $parentId)
    {
    }
}
