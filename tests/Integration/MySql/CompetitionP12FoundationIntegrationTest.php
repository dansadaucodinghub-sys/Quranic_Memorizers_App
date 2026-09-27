<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\MySql;

use PDO;
use Qmdb\Modules\ProductionHardening\Domain\ApiCredentialIssuer;
use Qmdb\Modules\ProductionHardening\Infrastructure\Persistence\MySqlProductionHardeningRepository;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Tests\Support\MySql\SchemaMySqlIntegrationTestCase;

final class CompetitionP12FoundationIntegrationTest extends SchemaMySqlIntegrationTestCase
{
    public function testP12FoundationHasTheMappedTablesAndImmutableEvidenceTriggers(): void
    {
        $database = $this->schemaProvider()->connection();
        $tables = $database->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('notifications','api_clients','webhook_deliveries','privacy_requests','data_holds','audit_events','outbox_events','idempotency_records','security_incidents','backup_artifacts','restore_verification_runs') AND ENGINE='InnoDB'");
        self::assertNotFalse($tables);
        self::assertSame(11, (int) $tables->fetchColumn());

        $triggers = $database->query("SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME LIKE 'trg_p12_%'");
        self::assertNotFalse($triggers);
        self::assertGreaterThanOrEqual(20, (int) $triggers->fetchColumn());
    }

    public function testP12GovernedCatalogsAreSeededWithoutRoleAssignmentsOrSecrets(): void
    {
        $database = $this->schemaProvider()->connection();
        self::assertSame(16, $this->scalarCount($database, "SELECT COUNT(*) FROM authorization_permissions WHERE owning_module='production.hardening'"));
        self::assertSame(4, $this->scalarCount($database, 'SELECT COUNT(*) FROM processing_purposes'));
        self::assertSame(4, $this->scalarCount($database, "SELECT COUNT(*) FROM privacy_notice_versions WHERE status_code='ACTIVE'"));
        self::assertSame(6, $this->scalarCount($database, "SELECT COUNT(*) FROM notification_templates WHERE status_code='ACTIVE'"));
        self::assertSame(4, $this->scalarCount($database, "SELECT COUNT(*) FROM retention_policy_records WHERE status_code='ACTIVE'"));
        self::assertSame(6, $this->scalarCount($database, "SELECT COUNT(*) FROM operational_service_catalog WHERE status_code='ACTIVE'"));
        self::assertSame(5, $this->scalarCount($database, "SELECT COUNT(*) FROM operational_sli_definitions WHERE status_code='ACTIVE'"));
        self::assertSame(0, $this->scalarCount($database, "SELECT COUNT(*) FROM api_credentials"));
        self::assertSame(0, $this->scalarCount($database, "SELECT COUNT(*) FROM webhook_subscriptions"));
    }

    public function testAccountOperationReceiptRejectsReplayAndRecordsCompletion(): void
    {
        $database = $this->schemaProvider()->connection();
        $provider = new class ($database) implements DatabaseConnectionProvider {
            public function __construct(private readonly PDO $database)
            {
            }

            public function connection(): PDO
            {
                return $this->database;
            }
        };
        $repository = new MySqlProductionHardeningRepository($provider, new ApiCredentialIssuer());
        $submission = '018f0d5e-7b2a-7cc0-8000-000000000001';
        $requestHash = hash('sha256', 'p12-operation');

        self::assertTrue($repository->claimAccountOperation(999_999, null, 'test.p12.operation', $submission, $requestHash));
        self::assertFalse($repository->claimAccountOperation(999_999, null, 'test.p12.operation', $submission, $requestHash));
        $repository->completeAccountOperation(999_999, 'test.p12.operation', $submission, 201, true);
        self::assertSame(1, $this->scalarCount($database, "SELECT COUNT(*) FROM idempotency_records WHERE actor_kind='ACCOUNT' AND actor_reference='999999' AND operation_code='test.p12.operation' AND status_code='COMPLETED' AND response_status=201"));

        $database->exec("DELETE FROM idempotency_records WHERE actor_kind='ACCOUNT' AND actor_reference='999999' AND operation_code='test.p12.operation'");
    }

    private function scalarCount(PDO $database, string $sql): int
    {
        $statement = $database->query($sql);
        self::assertNotFalse($statement);
        return (int) $statement->fetchColumn();
    }
}
