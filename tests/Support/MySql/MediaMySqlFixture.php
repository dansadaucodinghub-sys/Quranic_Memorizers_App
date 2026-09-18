<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\MySql;

use PDO;
use Qmdb\Modules\MediaCatalog\Infrastructure\Migration\CreateMediaFoundationMigration;
use Qmdb\Modules\MediaCatalog\Infrastructure\Migration\CreateMediaOperationReceiptMigration;
use Qmdb\Modules\MediaCatalog\Infrastructure\Migration\ExtendMediaEvidenceRuntimeMigration;
use Qmdb\Modules\MediaModeration\Infrastructure\Migration\CompleteMediaGovernanceMigration;
use Qmdb\Modules\MediaModeration\Infrastructure\Migration\CreateMediaConsentReviewMigration;
use Qmdb\Modules\MediaModeration\Infrastructure\Migration\ExtendMediaGovernanceSecurityMigration;

/** Restores only test fixtures after older serial tests deliberately dismantle dependent tables. */
final class MediaMySqlFixture
{
    public static function ensure(PDO $connection, string $projectRoot): void
    {
        $databaseStatement = $connection->query('SELECT DATABASE()');
        if ($databaseStatement === false) {
            throw new \RuntimeException('Test database identity could not be read.');
        }
        $database = $databaseStatement->fetchColumn();
        if (!is_string($database) || !str_contains(strtolower($database), 'test') || $connection->inTransaction()) {
            throw new \LogicException('Media schema fixtures require an idle dedicated test database.');
        }
        $tableStatement = $connection->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('media_assets','media_upload_sessions','media_upload_parts','media_variants','media_processing_jobs','media_scan_results','media_events','media_holds','media_delivery_policies','media_operation_receipts','media_governance_operations','media_consent_reviews')");
        if ($tableStatement === false) {
            throw new \RuntimeException('Media fixture inventory could not be read.');
        }
        $count = $tableStatement->fetchColumn();
        if ((int) $count === 12) {
            return;
        }
        (new AuthorizationMySqlFixture($connection, $projectRoot))->rebuild();
        foreach (
            [
            new CreateMediaFoundationMigration(),
            new ExtendMediaEvidenceRuntimeMigration(),
            new CreateMediaOperationReceiptMigration(),
            new CompleteMediaGovernanceMigration(),
            new ExtendMediaGovernanceSecurityMigration(),
            new CreateMediaConsentReviewMigration(),
            ] as $migration
        ) {
            foreach ($migration->up() as $step) {
                $connection->prepare($step->sql())->execute($step->parameters());
            }
        }
    }
}
