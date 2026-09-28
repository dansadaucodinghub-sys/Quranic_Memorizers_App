<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\MySql;

use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Tests\Support\MySql\MySqlIntegrationTestCase;
use Qmdb\Tests\Support\PilotOfflineRollout\P13EvidenceCatalog;

#[Group('P13')]
#[Group('P13FaultInjection')]
final class CompetitionP13FaultInjectionIntegrationTest extends MySqlIntegrationTestCase
{
    /** @return iterable<string,array{string}> */
    public static function boundaries(): iterable
    {
        foreach (array_keys((new P13EvidenceCatalog())->faults()) as $boundary) {
            yield $boundary => [$boundary];
        }
    }

    #[DataProvider('boundaries')]
    public function testFaultBoundaryRollsBackAndCleanRetrySucceeds(string $boundary): void
    {
        $database = $this->provider()->connection();
        $submission = UuidV7::generate()->toString();
        try {
            $database->beginTransaction();
            $this->insertEvidence($database, $boundary, $submission);
            throw new \RuntimeException('P13_TEST_FAULT:' . $boundary);
        } catch (\RuntimeException $error) {
            self::assertSame('P13_TEST_FAULT:' . $boundary, $error->getMessage());
            if ($database->inTransaction()) {
                $database->rollBack();
            }
        }
        self::assertSame(0, $this->countEvidence($database, $boundary, $submission));
        self::assertSame(1, $this->insertEvidence($database, $boundary, $submission));
        self::assertSame(1, $this->countEvidence($database, $boundary, $submission));

        $statement = $database->prepare('DELETE FROM p13_operation_receipts WHERE operation_code=:operation AND submission_id=:submission');
        self::assertInstanceOf(\PDOStatement::class, $statement);
        $statement->execute([':operation' => $this->operation($boundary), ':submission' => $submission]);
    }

    private function insertEvidence(PDO $database, string $boundary, string $submission): int
    {
        $statement = $database->prepare("INSERT INTO p13_operation_receipts (public_id,scope_kind,scope_reference_hash,operation_code,submission_id,request_hash,outcome_code,resource_public_id,created_at) VALUES (:public,'PLATFORM',UNHEX(SHA2(:scope,256)),:operation,:submission,UNHEX(SHA2(:request,256)),'SUCCEEDED',NULL,UTC_TIMESTAMP(6))");
        self::assertInstanceOf(\PDOStatement::class, $statement);
        $statement->execute([':public' => UuidV7::generate()->toBinary(), ':scope' => $boundary, ':operation' => $this->operation($boundary), ':submission' => $submission, ':request' => $boundary]);

        return $statement->rowCount();
    }

    private function countEvidence(PDO $database, string $boundary, string $submission): int
    {
        $statement = $database->prepare('SELECT COUNT(*) FROM p13_operation_receipts WHERE operation_code=:operation AND submission_id=:submission');
        self::assertInstanceOf(\PDOStatement::class, $statement);
        $statement->execute([':operation' => $this->operation($boundary), ':submission' => $submission]);

        return (int) $statement->fetchColumn();
    }

    private function operation(string $boundary): string
    {
        return 'P13_FAULT_' . strtoupper(str_replace('-', '_', $boundary));
    }
}
