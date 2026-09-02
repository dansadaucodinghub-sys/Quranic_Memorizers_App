<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Application;

use Qmdb\Modules\IdentityResolution\Configuration\IdentityResolutionConfiguration;
use Qmdb\Shared\Schema\Health\SchemaHealthCheck;
use Throwable;

final readonly class PeopleIdentityResolutionReadinessCheck
{
    public function __construct(private IdentityResolutionConfiguration $configuration, private SchemaHealthCheck $schema, private IdentityResolutionRepository $repository)
    {
    }

    public function isReady(): bool
    {
        try {
            return $this->configuration->pairingEntropyBits >= 128 && $this->configuration->pairingTtlSeconds > 0 && $this->configuration->claimTtlSeconds >= $this->configuration->pairingTtlSeconds && $this->configuration->maintenanceBatchSize > 0 && $this->schema->check()->isReady() && $this->repository->report()['invalid_rows'] === 0;
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array{active_pairings:int,pending_claims:int,accepted_claims:int,active_assertions:int,open_cases:int,blocked_cases:int,resolved_cases:int,aliases:int,invalid_rows:int} */
    public function report(): array
    {
        return $this->repository->report();
    }
}
