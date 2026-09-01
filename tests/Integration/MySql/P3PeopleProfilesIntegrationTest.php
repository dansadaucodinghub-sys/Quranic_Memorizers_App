<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\MySql;

use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\Group;
use Qmdb\Tests\Support\MySql\AuthorizationMySqlFixture;
use Qmdb\Tests\Support\MySql\MySqlIntegrationTestCase;

#[Group('PeopleProfiles')]
#[Group('Concurrency')]
final class P3PeopleProfilesIntegrationTest extends MySqlIntegrationTestCase
{
    private PDO $connection;
    private AuthorizationMySqlFixture $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->provider()->connection();
        $this->fixture = new AuthorizationMySqlFixture($this->connection, dirname(__DIR__, 3));
        $this->fixture->rebuild();
    }

    protected function tearDown(): void
    {
        $this->fixture->dropBusinessTables();
        parent::tearDown();
    }

    public function testDatabaseConstraintsPreserveSelfLinkAndActiveGuardianshipInvariants(): void
    {
        [$account] = $this->fixture->account();
        $guardian = $this->insertPerson($account, 'QMP-AAAAAAAAAAAAAAAB');
        $dependent = $this->insertPerson($account, 'QMP-AAAAAAAAAAAAAAAC');
        $this->insertSelfLink($account, $guardian);

        $this->expectException(PDOException::class);
        $this->insertSelfLink($account, $dependent);
    }

    public function testGuardianshipConstraintsRejectSelfAndDuplicateActiveRelationships(): void
    {
        [$account] = $this->fixture->account();
        $guardian = $this->insertPerson($account, 'QMP-AAAAAAAAAAAAAAAD');
        $dependent = $this->insertPerson($account, 'QMP-AAAAAAAAAAAAAAAE');
        $this->insertGuardianship($account, $guardian, $dependent);
        self::assertSame(1, $this->tableRowCount('people_guardianships'));

        try {
            $this->insertGuardianship($account, $guardian, $dependent);
            self::fail('Duplicate active guardianship was accepted.');
        } catch (PDOException) {
            self::addToAssertionCount(1);
        }
        try {
            $this->insertGuardianship($account, $guardian, $guardian);
            self::fail('Self guardianship was accepted.');
        } catch (PDOException) {
            self::addToAssertionCount(1);
        }
    }

    public function testConcurrentSelfProfileCreationLeavesExactlyOneActiveLink(): void
    {
        [$account] = $this->fixture->account();
        $results = $this->runWorkers([
            ['account_id' => $account, 'registry_code' => 'QMP-AAAAAAAAAAAAAAAJ'],
            ['account_id' => $account, 'registry_code' => 'QMP-AAAAAAAAAAAAAAAK'],
        ]);
        sort($results);

        self::assertSame([false, true], $results);
        self::assertSame(1, $this->tableRowCount("people_account_links WHERE account_id = {$account} AND status = 'ACTIVE'"));
        self::assertSame(1, $this->tableRowCount('people_persons'));
    }

    /** @param list<array{account_id:int,registry_code:string}> $payloads
     * @return list<bool>
     */
    private function runWorkers(array $payloads): array
    {
        $processes = [];
        $worker = dirname(__DIR__, 2) . '/Support/MySql/P3PeopleMutationWorker.php';
        foreach ($payloads as $payload) {
            $pipes = [];
            $process = proc_open([PHP_BINARY, $worker], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 3), null, ['bypass_shell' => true]);
            self::assertIsResource($process);
            fwrite($pipes[0], json_encode($payload, JSON_THROW_ON_ERROR));
            fclose($pipes[0]);
            $processes[] = [$process, $pipes];
        }
        $results = [];
        foreach ($processes as [$process, $pipes]) {
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(0, proc_close($process), is_string($stderr) ? $stderr : '');
            $decoded = json_decode(is_string($stdout) ? $stdout : '', true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($decoded);
            self::assertIsBool($decoded['mutated'] ?? null);
            $results[] = $decoded['mutated'];
        }

        return $results;
    }

    private function insertPerson(int $account, string $registryCode): int
    {
        $statement = $this->connection->prepare("INSERT INTO people_persons (public_id, registry_code, status, sex_classification, created_by_account_id, version, created_at, updated_at) VALUES (UUID_TO_BIN(UUID()), :registry_code, 'ACTIVE', 'NOT_RECORDED', :account_id, 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))");
        $statement->execute([':registry_code' => $registryCode, ':account_id' => $account]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertSelfLink(int $account, int $person): void
    {
        $statement = $this->connection->prepare("INSERT INTO people_account_links (public_id, account_id, person_id, link_type, status, version, linked_at, created_at, updated_at) VALUES (UUID_TO_BIN(UUID()), :account_id, :person_id, 'SELF', 'ACTIVE', 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))");
        $statement->execute([':account_id' => $account, ':person_id' => $person]);
    }

    private function insertGuardianship(int $account, int $guardian, int $dependent): void
    {
        $statement = $this->connection->prepare("INSERT INTO people_guardianships (public_id, guardian_person_id, dependent_person_id, relationship_type, authority_scope, authority_basis, status, version, created_by_account_id, confirmed_at, created_at, updated_at) VALUES (UUID_TO_BIN(UUID()), :guardian, :dependent, 'PARENT', 'PROFILE_MANAGEMENT', 'SELF_DECLARED', 'ACTIVE', 1, :account_id, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))");
        $statement->execute([':guardian' => $guardian, ':dependent' => $dependent, ':account_id' => $account]);
    }

    private function tableRowCount(string $from): int
    {
        $statement = $this->connection->query('SELECT COUNT(*) FROM ' . $from);
        self::assertNotFalse($statement);

        return (int) $statement->fetchColumn();
    }
}
