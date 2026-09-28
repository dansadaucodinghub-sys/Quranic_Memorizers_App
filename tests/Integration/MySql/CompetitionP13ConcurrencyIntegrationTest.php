<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\MySql;

use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Tests\Support\MySql\MySqlIntegrationTestCase;

#[Group('P13')]
#[Group('P13Concurrency')]
final class CompetitionP13ConcurrencyIntegrationTest extends MySqlIntegrationTestCase
{
    /** @return iterable<string,array{int,string,string}> */
    public static function scenarios(): iterable
    {
        yield 'pilot-site assignment' => [1, 'pilot-site-assignment', 'uq_p13_pilot_site'];
        yield 'readiness and pilot start' => [2, 'readiness-pilot-start', 'ix_p13_readiness_current'];
        yield 'rollout assignment' => [3, 'rollout-assignment', 'uq_p13_rollout_assignment_scope'];
        yield 'go-no-go decision' => [4, 'go-no-go-decision', 'ix_p13_rollout_decision_wave'];
        yield 'key rotation and authentication' => [5, 'key-rotation-authentication', 'ix_p13_device_key_active'];
        yield 'package supersession and activation' => [6, 'package-supersession-activation', 'ix_p13_package_current'];
        yield 'conflict assignment and review' => [7, 'conflict-assignment-review', 'ix_p13_conflict_assignment'];
        yield 'conflict decisions' => [8, 'conflict-decisions', 'uq_p13_conflict_decision_conflict'];
        yield 'duplicate P7 UUID' => [9, 'duplicate-p7-operation', 'uq_p13_sync_receipt_change'];
        yield 'offline and online P7' => [10, 'offline-online-p7', 'uq_p7_operation_submission'];
        yield 'concurrent score drafts' => [11, 'concurrent-score-drafts', 'uq_p6_sheet_public'];
        yield 'offline and online score submission' => [12, 'offline-online-score-submission', 'uq_p6_operation_submission'];
    }

    #[DataProvider('scenarios')]
    public function testDeterministicScenario(int $scenario, string $code, string $requiredIndex): void
    {
        $first = $this->provider()->connection();
        $second = $this->provider()->connection();
        self::assertNotSame($this->connectionId($first), $this->connectionId($second));
        self::assertSame(1, $this->advisoryLock($first, 'qmdb.p13.concurrency.' . $scenario, 0));
        self::assertSame(0, $this->advisoryLock($second, 'qmdb.p13.concurrency.' . $scenario, 0));

        $submission = UuidV7::generate()->toString();
        try {
            $first->beginTransaction();
            self::assertSame(1, $this->insertClaim($first, $code, $submission));
            $first->commit();
            self::assertSame(1, $this->releaseLock($first, 'qmdb.p13.concurrency.' . $scenario));
            self::assertSame(0, $this->insertClaim($second, $code, $submission));
            self::assertSame(1, $this->receiptCount($second, $code, $submission));
            self::assertGreaterThanOrEqual(1, $this->indexCount($second, $requiredIndex));
        } finally {
            if ($first->inTransaction()) {
                $first->rollBack();
            }
            $this->releaseLock($first, 'qmdb.p13.concurrency.' . $scenario);
            $statement = $second->prepare('DELETE FROM p13_operation_receipts WHERE operation_code=:operation AND submission_id=:submission');
            self::assertInstanceOf(\PDOStatement::class, $statement);
            $statement->execute([':operation' => 'P13_CONCURRENCY_' . strtoupper(str_replace('-', '_', $code)), ':submission' => $submission]);
        }
    }

    private function insertClaim(PDO $database, string $code, string $submission): int
    {
        $operation = 'P13_CONCURRENCY_' . strtoupper(str_replace('-', '_', $code));
        $statement = $database->prepare("INSERT IGNORE INTO p13_operation_receipts (public_id,scope_kind,scope_reference_hash,operation_code,submission_id,request_hash,outcome_code,resource_public_id,created_at) VALUES (:public,'PLATFORM',UNHEX(SHA2(:scope,256)),:operation,:submission,UNHEX(SHA2(:request,256)),'SUCCEEDED',NULL,UTC_TIMESTAMP(6))");
        self::assertInstanceOf(\PDOStatement::class, $statement);
        $statement->execute([':public' => UuidV7::generate()->toBinary(), ':scope' => $code, ':operation' => $operation, ':submission' => $submission, ':request' => $code]);

        return $statement->rowCount();
    }

    private function receiptCount(PDO $database, string $code, string $submission): int
    {
        $statement = $database->prepare('SELECT COUNT(*) FROM p13_operation_receipts WHERE operation_code=:operation AND submission_id=:submission');
        self::assertInstanceOf(\PDOStatement::class, $statement);
        $statement->execute([':operation' => 'P13_CONCURRENCY_' . strtoupper(str_replace('-', '_', $code)), ':submission' => $submission]);

        return (int) $statement->fetchColumn();
    }

    private function connectionId(PDO $database): int
    {
        $statement = $database->query('SELECT CONNECTION_ID()');
        self::assertInstanceOf(\PDOStatement::class, $statement);

        return (int) $statement->fetchColumn();
    }

    private function advisoryLock(PDO $database, string $name, int $timeout): int
    {
        $statement = $database->prepare('SELECT GET_LOCK(:name,:timeout)');
        self::assertInstanceOf(\PDOStatement::class, $statement);
        $statement->execute([':name' => $name, ':timeout' => $timeout]);

        return (int) $statement->fetchColumn();
    }

    private function releaseLock(PDO $database, string $name): int
    {
        $statement = $database->prepare('SELECT RELEASE_LOCK(:name)');
        self::assertInstanceOf(\PDOStatement::class, $statement);
        $statement->execute([':name' => $name]);

        return (int) $statement->fetchColumn();
    }

    private function indexCount(PDO $database, string $index): int
    {
        $statement = $database->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND index_name=:index_name');
        self::assertInstanceOf(\PDOStatement::class, $statement);
        $statement->execute([':index_name' => $index]);

        return (int) $statement->fetchColumn();
    }
}
