<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\MySql;

use PDO;
use Qmdb\Tests\Support\MySql\SchemaMySqlIntegrationTestCase;

final class CompetitionP11FoundationIntegrationTest extends SchemaMySqlIntegrationTestCase
{
    private static ?PDO $database = null;

    public static function tearDownAfterClass(): void
    {
        if (self::$database instanceof PDO) {
            foreach (
                [
                    'p11_notification_intents', 'p11_operation_receipts', 'reporting_events',
                    'export_artifacts', 'export_jobs', 'report_runs', 'report_definitions',
                    'analytics_snapshot_events', 'analytics_snapshot_values', 'analytics_snapshot_runs',
                    'analytics_dashboard_widgets', 'analytics_dashboard_definitions',
                    'analytics_metric_definitions', 'analytics_privacy_policies',
                    'search_projection_checkpoints', 'search_projection_events', 'search_projection_documents',
                ] as $table
            ) {
                self::$database->exec('DROP TABLE IF EXISTS ' . $table);
            }
        }
        self::$database = null;
        parent::tearDownAfterClass();
    }

    public function testP11FoundationHasRequiredInnoDbTablesAndImmutabilityTriggers(): void
    {
        $database = $this->schemaProvider()->connection();
        self::$database = $database;
        $tables = $database->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('search_projection_documents','analytics_snapshot_runs','analytics_snapshot_values','report_runs','export_artifacts') AND ENGINE='InnoDB'");
        self::assertNotFalse($tables);
        self::assertSame(5, (int) $tables->fetchColumn());

        $triggers = $database->query("SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME LIKE 'trg_p11_%' ORDER BY TRIGGER_NAME");
        self::assertNotFalse($triggers);
        self::assertSame([
            'trg_p11_export_artifact_guard',
            'trg_p11_reporting_event_no_delete',
            'trg_p11_reporting_event_no_update',
            'trg_p11_search_event_no_delete',
            'trg_p11_search_event_no_update',
            'trg_p11_snapshot_completed_no_delete',
            'trg_p11_snapshot_completed_no_update',
            'trg_p11_snapshot_value_no_delete',
            'trg_p11_snapshot_value_no_update',
        ], $triggers->fetchAll(PDO::FETCH_COLUMN));
    }

    public function testP11AuthorizationAndGovernedCatalogsAreSeededWithoutAssignments(): void
    {
        $database = $this->schemaProvider()->connection();
        self::$database = $database;
        self::assertSame(13, $this->scalarCount($database, "SELECT COUNT(*) FROM authorization_permissions WHERE owning_module='search.analytics_reporting'"));
        self::assertSame(5, $this->scalarCount($database, "SELECT COUNT(*) FROM analytics_metric_definitions WHERE status_code='ACTIVE'"));
        self::assertSame(3, $this->scalarCount($database, "SELECT COUNT(*) FROM report_definitions WHERE status_code='ACTIVE'"));
        self::assertSame(0, $this->scalarCount($database, "SELECT COUNT(*) FROM workspace_role_assignments a INNER JOIN authorization_roles r ON r.id=a.role_id WHERE r.code IN ('workspace.analytics_viewer','workspace.report_operator','workspace.report_approver')"));
    }

    private function scalarCount(PDO $database, string $sql): int
    {
        $statement = $database->query($sql);
        self::assertNotFalse($statement);
        return (int) $statement->fetchColumn();
    }
}
