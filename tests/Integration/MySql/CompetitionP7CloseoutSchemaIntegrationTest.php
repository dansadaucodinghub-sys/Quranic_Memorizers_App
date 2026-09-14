<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\MySql;

use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\Group;
use Qmdb\Tests\Support\MySql\MySqlIntegrationTestCase;

#[Group('P7Closeout')]
#[Group('P7Concurrency')]
#[Group('P7FaultInjection')]
#[Group('P7Performance')]
final class CompetitionP7CloseoutSchemaIntegrationTest extends MySqlIntegrationTestCase
{
    public function testP7TablesUseInnoDbAndExposeImmutableAndBoundedContracts(): void
    {
        $pdo = $this->provider()->connection();
        $tables = [
            'competition_live_events', 'competition_live_projection_snapshots',
            'competition_result_publication_events', 'competition_result_packages',
            'competition_result_publication_projections', 'competition_appeal_decisions',
            'competition_p7_outbox_messages', 'competition_p7_operations',
        ];
        $statement = $pdo->prepare('SELECT ENGINE AS storage_engine, TABLE_COLLATION AS table_collation FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table');
        self::assertInstanceOf(\PDOStatement::class, $statement);
        foreach ($tables as $table) {
            $statement->execute([':table' => $table]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            self::assertIsArray($row, $table);
            self::assertSame('InnoDB', $row['storage_engine']);
            self::assertSame('utf8mb4_0900_ai_ci', $row['table_collation']);
        }

        $triggers = $pdo->query("SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND trigger_name IN ('trg_p7_live_events_no_update','trg_p7_live_events_no_delete','trg_p7_projection_snapshots_no_update','trg_p7_projection_snapshots_no_delete','trg_p7_publication_events_no_update','trg_p7_publication_events_no_delete','trg_p7_result_packages_no_update','trg_p7_result_packages_no_delete','trg_p7_result_projection_no_update','trg_p7_result_projection_no_delete','trg_p7_appeal_decisions_no_update','trg_p7_appeal_decisions_no_delete')");
        self::assertInstanceOf(\PDOStatement::class, $triggers);
        $triggerCount = (int) $triggers->fetchColumn();
        self::assertSame(12, $triggerCount);
        $constraints = $pdo->query("SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND constraint_type='CHECK' AND constraint_name IN ('ck_p7_outbox','ck_p7_operation','ck_p7_publication_projection','ck_p7_publication_projection_head')");
        self::assertInstanceOf(\PDOStatement::class, $constraints);
        $constraintCount = (int) $constraints->fetchColumn();
        self::assertSame(4, $constraintCount);
    }

    public function testOperationReceiptUniquenessRejectsReplayAfterCommit(): void
    {
        $pdo = $this->provider()->connection();
        $workspacePublicId = random_bytes(16);
        $workspaceCode = 'p7-closeout-' . bin2hex(random_bytes(6));
        $submission = random_bytes(16);
        $operation = $pdo->prepare("INSERT INTO competition_p7_operations (public_id,submission_id,workspace_id,operation_code,request_fingerprint,aggregate_kind,aggregate_public_id,result_status,version_after,occurred_at) VALUES (:public_id,:submission_id,:workspace_id,'P7_CLOSEOUT_TEST',:fingerprint,'RESULT_PUBLICATION',:aggregate_public_id,'PREPARED',1,UTC_TIMESTAMP(6))");
        self::assertInstanceOf(\PDOStatement::class, $operation);
        $workspace = $pdo->prepare("INSERT INTO workspaces (public_id,workspace_code,name,status_code,version,created_at,updated_at) VALUES (:public_id,:workspace_code,'P7 closeout temporary workspace','ACTIVE',1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");
        self::assertInstanceOf(\PDOStatement::class, $workspace);

        $workspace->execute([':public_id' => $workspacePublicId, ':workspace_code' => $workspaceCode]);
        $workspaceId = (int) $pdo->lastInsertId();
        try {
            $parameters = [
                ':public_id' => random_bytes(16),
                ':submission_id' => $submission,
                ':workspace_id' => $workspaceId,
                ':fingerprint' => hash('sha256', 'p7-closeout-replay', true),
                ':aggregate_public_id' => random_bytes(16),
            ];
            $operation->execute($parameters);
            try {
                $parameters[':public_id'] = random_bytes(16);
                $operation->execute($parameters);
                self::fail('A duplicate P7 operation submission was accepted.');
            } catch (PDOException) {
                self::addToAssertionCount(1);
            }
        } finally {
            $deleteOperation = $pdo->prepare('DELETE FROM competition_p7_operations WHERE workspace_id=:workspace_id');
            self::assertInstanceOf(\PDOStatement::class, $deleteOperation);
            $deleteOperation->execute([':workspace_id' => $workspaceId]);
            $deleteWorkspace = $pdo->prepare('DELETE FROM workspaces WHERE id=:workspace_id');
            self::assertInstanceOf(\PDOStatement::class, $deleteWorkspace);
            $deleteWorkspace->execute([':workspace_id' => $workspaceId]);
        }
    }

    public function testOutboxDueLookupUsesTheBoundedCompositeIndex(): void
    {
        $pdo = $this->provider()->connection();
        $statement = $pdo->query("EXPLAIN FORMAT=JSON SELECT id FROM competition_p7_outbox_messages WHERE status='PENDING' AND available_at<=UTC_TIMESTAMP(6) ORDER BY available_at,id LIMIT 50");
        self::assertInstanceOf(\PDOStatement::class, $statement);
        $plan = $statement->fetchColumn();
        self::assertIsString($plan);
        self::assertStringContainsString('ix_p7_outbox_due', $plan);
        self::assertStringContainsString('"using_filesort": false', $plan);
    }
}
