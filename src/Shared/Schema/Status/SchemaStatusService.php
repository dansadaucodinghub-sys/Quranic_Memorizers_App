<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Status;

use Qmdb\Shared\Schema\Metadata\SchemaMetadataReport;
use Qmdb\Shared\Schema\Metadata\SchemaMetadataVerifier;
use Qmdb\Shared\Schema\Migration\MigrationPlan;
use Qmdb\Shared\Schema\Migration\MigrationPlanner;
use Qmdb\Shared\Schema\Migration\MigrationRegistry;
use Qmdb\Shared\Schema\Seed\SeedPlan;
use Qmdb\Shared\Schema\Seed\SeedPlanner;
use Qmdb\Shared\Schema\Seed\SeedRegistry;
use Qmdb\Shared\Schema\State\SchemaStateRepository;

final readonly class SchemaStatusService
{
    public function __construct(
        private SchemaMetadataVerifier $verifier,
        private SchemaStateRepository $repository,
        private MigrationRegistry $migrations,
        private MigrationPlanner $migrationPlanner,
        private SeedRegistry $seeds,
        private SeedPlanner $seedPlanner,
    ) {
    }

    public function verify(): SchemaMetadataReport
    {
        return $this->verifier->verify();
    }

    public function migrationPlan(): MigrationPlan
    {
        return $this->migrationPlanner->plan($this->migrations, $this->repository->migrationRecords());
    }

    public function seedPlan(): SeedPlan
    {
        return $this->seedPlanner->plan($this->seeds, $this->repository->seedRecords());
    }
}
