<?php

declare(strict_types=1);

namespace Qmdb\Modules\OrganizationAffiliations\Application;

use Qmdb\Modules\OrganizationAffiliations\Configuration\OrganizationAffiliationsConfiguration;
use Qmdb\Shared\Schema\Health\SchemaHealthCheck;
use Throwable;

final readonly class OrganizationAffiliationsReadinessCheck
{
    public function __construct(private OrganizationAffiliationsConfiguration $configuration, private SchemaHealthCheck $schema, private OrganizationAffiliationRepository $repository)
    {
    }
    public function isReady(): bool
    {
        try {
            $report = $this->repository->report();
            return $this->configuration->codeEntropyBits >= 80 && $this->configuration->maximumRoles > 0 && $this->configuration->maximumUnits > 0 && $this->schema->check()->isReady() && $report['role_definitions'] === 12 && $report['invalid_rows'] === 0;
        } catch (Throwable) {
            return false;
        }
    }
    /** @return array{role_definitions:int,pending:int,active:int,suspended:int,ended:int,invalid_rows:int} */
    public function report(): array
    {
        return $this->repository->report();
    }
}
