<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Health;

use Qmdb\Shared\Schema\Metadata\SchemaMetadataStatus;
use Qmdb\Shared\Schema\Metadata\SchemaMetadataInspection;
use Qmdb\Shared\Schema\Migration\MigrationPlanStatus;
use Qmdb\Shared\Schema\Migration\MigrationPlanner;
use Qmdb\Shared\Schema\Migration\MigrationRegistry;
use Qmdb\Shared\Schema\Migration\MigrationStatus;
use Qmdb\Shared\Schema\State\SchemaStateReader;
use Throwable;

final readonly class SchemaHealthCheck
{
    public function __construct(
        private SchemaMetadataInspection $verifier,
        private SchemaStateReader $reader,
        private MigrationRegistry $registry,
        private MigrationPlanner $planner,
    ) {
    }

    public function check(): SchemaHealthReport
    {
        $metadata = $this->verifier->verify();
        if (!$metadata->isReady()) {
            return new SchemaHealthReport(match ($metadata->status) {
                SchemaMetadataStatus::NOT_INSTALLED => SchemaHealthStatus::NOT_INSTALLED,
                SchemaMetadataStatus::INCOMPATIBLE => SchemaHealthStatus::INCOMPATIBLE_LEDGER,
                default => SchemaHealthStatus::UNAVAILABLE,
            });
        }
        try {
            $records = $this->reader->migrationRecords();
            foreach ($records as $record) {
                if ($record->status === MigrationStatus::ROLLBACK_FAILED) {
                    return new SchemaHealthReport(SchemaHealthStatus::ROLLBACK_FAILED);
                }
            }
            $plan = $this->planner->plan($this->registry, $records);
            foreach ($plan->items as $item) {
                $status = match ($item->status) {
                    MigrationPlanStatus::PENDING,
                    MigrationPlanStatus::ROLLED_BACK,
                    MigrationPlanStatus::BLOCKED_BY_DEPENDENCY => SchemaHealthStatus::PENDING_MIGRATIONS,
                    MigrationPlanStatus::PARTIAL => SchemaHealthStatus::PARTIAL_MIGRATION,
                    MigrationPlanStatus::FAILED => SchemaHealthStatus::FAILED_MIGRATION,
                    MigrationPlanStatus::DRIFTED => SchemaHealthStatus::DRIFT_DETECTED,
                    MigrationPlanStatus::ORPHANED => SchemaHealthStatus::ORPHANED_APPLIED_MIGRATION,
                    MigrationPlanStatus::APPLIED => null,
                };
                if ($status instanceof SchemaHealthStatus) {
                    return new SchemaHealthReport($status);
                }
            }

            return new SchemaHealthReport(SchemaHealthStatus::READY);
        } catch (Throwable) {
            return new SchemaHealthReport(SchemaHealthStatus::UNAVAILABLE);
        }
    }
}
