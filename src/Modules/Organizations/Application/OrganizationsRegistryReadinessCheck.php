<?php

declare(strict_types=1);

namespace Qmdb\Modules\Organizations\Application;

use Qmdb\Modules\Organizations\Configuration\OrganizationsRegistryConfiguration;
use Qmdb\Shared\Schema\Health\SchemaHealthCheck;
use Throwable;

final readonly class OrganizationsRegistryReadinessCheck
{
    public function __construct(private OrganizationsRegistryConfiguration $configuration, private SchemaHealthCheck $schema, private OrganizationRegistryRepository $repository)
    {
    }

    public function isReady(): bool
    {
        try {
            return $this->configuration->unitMaximumDepth === 8 && $this->configuration->nameMaximumBytes === 240 && $this->configuration->maximumClassifications === 10 && $this->configuration->maximumUnits === 5000 && $this->schema->check()->isReady() && $this->repository->report()['invalid_rows'] === 0;
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array{organizations: int, units: int, invalid_rows: int} */
    public function report(): array
    {
        return $this->repository->report();
    }
}
