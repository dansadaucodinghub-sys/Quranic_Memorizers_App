<?php
declare(strict_types=1);
namespace Qmdb\Modules\Organizations\Configuration;
final readonly class OrganizationsRegistryConfiguration
{
    public function __construct(public int $unitMaximumDepth, public int $nameMaximumBytes, public int $searchNameMaximumBytes, public int $maximumClassifications, public int $maximumUnits, public int $mutationWindowSeconds, public int $mutationMaximumAttempts, public int $unitMutationWindowSeconds, public int $unitMutationMaximumAttempts) {}
}
