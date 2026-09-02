<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\MySql;

use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\Group;
use Qmdb\Modules\OrganizationAffiliations\Infrastructure\Persistence\MySqlOrganizationAffiliationRepository;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Tests\Support\MySql\AuthorizationMySqlFixture;
use Qmdb\Tests\Support\MySql\MySqlIntegrationTestCase;

#[Group('OrganizationAffiliations')]
#[Group('TenantIsolation')]
#[Group('Concurrency')]
final class P3OrganizationAffiliationsIntegrationTest extends MySqlIntegrationTestCase
{
    private PDO $connection;
    private AuthorizationMySqlFixture $fixture;
    private bool $fixtureReady = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->provider()->connection();
        $this->fixture = new AuthorizationMySqlFixture($this->connection, dirname(__DIR__, 3));
        $this->fixture->rebuild();
        $this->fixtureReady = true;
    }

    protected function tearDown(): void
    {
        if ($this->fixtureReady) {
            $this->fixture->dropBusinessTables();
        }
        parent::tearDown();
    }

    public function testTenantScopedRepositoryAndCompositeForeignKeyRejectCrossWorkspaceAccess(): void
    {
        [$account] = $this->fixture->account();
        [, , $workspaceA] = $this->fixture->workspace();
        [, , $workspaceB] = $this->fixture->workspace();
        $organizationId = $this->insertOrganization($workspaceA->workspaceInternalId(), $account, 'A');
        $personId = $this->insertPerson($account, 'QMP-AAAAAAAAAAAAAAAB');
        $affiliationId = $this->insertAffiliation($workspaceA->workspaceInternalId(), $organizationId, $personId, $account, 'A');
        $repository = new MySqlOrganizationAffiliationRepository($this->provider());
        $organization = $repository->organization($workspaceA, $organizationId);

        self::assertNotNull($organization);
        self::assertNotNull($repository->affiliation($workspaceA, $organization, $affiliationId));
        self::assertNull($repository->organization($workspaceB, $organizationId));
        self::assertNull($repository->affiliation($workspaceB, ['id' => (int) $organization['id']], $affiliationId));

        $statement = $this->connection->prepare(
            "INSERT INTO organization_affiliations (public_id,affiliation_code,workspace_id,organization_id,person_id,status,requested_by_account_id,request_expires_at,requested_at,activated_at,version,created_at,updated_at) VALUES (UUID_TO_BIN(UUID()),:code,:workspace_id,(SELECT id FROM organizations WHERE public_id=UUID_TO_BIN(:organization_id)),:person_id,'ACTIVE',:account_id,DATE_ADD(UTC_TIMESTAMP(6), INTERVAL 30 DAY),UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))",
        );
        $this->expectException(PDOException::class);
        $statement->execute([
            'code' => 'QMA-CROSS' . bin2hex(random_bytes(6)),
            'workspace_id' => $workspaceB->workspaceInternalId(),
            'organization_id' => $organizationId,
            'person_id' => $personId,
            'account_id' => $account,
        ]);
    }

    public function testOpenAffiliationUniquenessAndStatusHistoryImmutabilityAreEnforcedByMysql(): void
    {
        [$account] = $this->fixture->account();
        [, , $workspace] = $this->fixture->workspace();
        $organizationId = $this->insertOrganization($workspace->workspaceInternalId(), $account, 'U');
        $personId = $this->insertPerson($account, 'QMP-AAAAAAAAAAAAAAAC');
        $affiliationId = $this->insertAffiliation($workspace->workspaceInternalId(), $organizationId, $personId, $account, 'U');
        $this->expectException(PDOException::class);
        $this->insertAffiliation($workspace->workspaceInternalId(), $organizationId, $personId, $account, 'D');
    }

    public function testLifecycleHistoryCannotBeUpdatedOrDeletedAfterInsertion(): void
    {
        [$account] = $this->fixture->account();
        [$workspaceInternalId] = $this->fixture->workspace();
        $organizationId = $this->insertOrganization($workspaceInternalId, $account, 'H');
        $personId = $this->insertPerson($account, 'QMP-AAAAAAAAAAAAAAAD');
        $this->insertAffiliation($workspaceInternalId, $organizationId, $personId, $account, 'H');
        $affiliationInternalId = (int) $this->connection->lastInsertId();
        $eventId = UuidV7::generate()->toString();
        $event = $this->connection->prepare(
            "INSERT INTO organization_affiliation_status_events (public_id,workspace_id,organization_id,affiliation_id,from_status,to_status,actor_authority,actor_account_id,guardianship_id,reason_code,correlation_id,occurred_at,created_at) VALUES (UUID_TO_BIN(:public_id),:workspace_id,(SELECT id FROM organizations WHERE public_id=UUID_TO_BIN(:organization_id)),:affiliation_id,NULL,'ACTIVE','SYSTEM',NULL,NULL,'TEST_EVENT',NULL,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))",
        );
        $event->execute(['public_id' => $eventId, 'workspace_id' => $workspaceInternalId, 'organization_id' => $organizationId, 'affiliation_id' => $affiliationInternalId]);

        $update = $this->connection->prepare('UPDATE organization_affiliation_status_events SET reason_code = :reason WHERE public_id = UUID_TO_BIN(:public_id)');
        try {
            $update->execute(['reason' => 'TAMPERED', 'public_id' => $eventId]);
            self::fail('Mutable lifecycle history was accepted.');
        } catch (PDOException) {
            self::addToAssertionCount(1);
        }
        $delete = $this->connection->prepare('DELETE FROM organization_affiliation_status_events WHERE public_id = UUID_TO_BIN(:public_id)');
        try {
            $delete->execute(['public_id' => $eventId]);
            self::fail('Deletable lifecycle history was accepted.');
        } catch (PDOException) {
            self::addToAssertionCount(1);
        }
    }

    public function testConcurrentOptimisticAffiliationMutationsHaveOneWinner(): void
    {
        [$account] = $this->fixture->account();
        [$workspaceInternalId] = $this->fixture->workspace();
        $organizationId = $this->insertOrganization($workspaceInternalId, $account, 'C');
        $personId = $this->insertPerson($account, 'QMP-AAAAAAAAAAAAAAAE');
        $affiliationId = $this->insertAffiliation($workspaceInternalId, $organizationId, $personId, $account, 'C');
        $payload = ['workspace_id' => $workspaceInternalId, 'affiliation_id' => $affiliationId, 'expected_version' => 1];
        $results = $this->runWorkers([$payload, $payload]);
        sort($results);

        self::assertSame([false, true], $results);
        $statement = $this->connection->prepare('SELECT version FROM organization_affiliations WHERE public_id = UUID_TO_BIN(:public_id)');
        $statement->execute(['public_id' => $affiliationId]);
        self::assertSame(2, (int) $statement->fetchColumn());
    }

    private function insertOrganization(int $workspaceId, int $accountId, string $suffix): string
    {
        $organizationId = UuidV7::generate()->toString();
        $organization = $this->connection->prepare("INSERT INTO organizations (public_id,workspace_id,registry_code,status,created_by_account_id,version,created_at,updated_at) VALUES (UUID_TO_BIN(:public_id),:workspace_id,:code,'ACTIVE',:account_id,1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");
        $organization->execute(['public_id' => $organizationId, 'workspace_id' => $workspaceId, 'code' => 'QMO-' . $suffix . bin2hex(random_bytes(7)), 'account_id' => $accountId]);
        $internalId = (int) $this->connection->lastInsertId();
        $name = $this->connection->prepare("INSERT INTO organization_names (public_id,workspace_id,organization_id,name_type,script_code,display_name,search_name,status,version,effective_at,created_at,updated_at) VALUES (UUID_TO_BIN(UUID()),:workspace_id,:organization_id,'PRIMARY','LATIN',:name,:search_name,'ACTIVE',1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");
        $name->execute(['workspace_id' => $workspaceId, 'organization_id' => $internalId, 'name' => 'Affiliation Test ' . $suffix, 'search_name' => 'affiliation test ' . strtolower($suffix)]);
        $jurisdiction = $this->connection->prepare("INSERT INTO organization_jurisdictions (public_id,workspace_id,organization_id,jurisdiction_level,country_id,level_one_area_id,level_two_area_id,source_type,status,version,effective_at,created_at,updated_at) VALUES (UUID_TO_BIN(UUID()),:workspace_id,:organization_id,'NOT_RECORDED',NULL,NULL,NULL,'SELF_DECLARED','ACTIVE',1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");
        $jurisdiction->execute(['workspace_id' => $workspaceId, 'organization_id' => $internalId]);

        return $organizationId;
    }

    private function insertPerson(int $accountId, string $registryCode): int
    {
        $statement = $this->connection->prepare("INSERT INTO people_persons (public_id,registry_code,status,sex_classification,created_by_account_id,version,created_at,updated_at) VALUES (UUID_TO_BIN(UUID()),:registry_code,'ACTIVE','NOT_RECORDED',:account_id,1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");
        $statement->execute(['registry_code' => $registryCode, 'account_id' => $accountId]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertAffiliation(int $workspaceId, string $organizationId, int $personId, int $accountId, string $suffix): string
    {
        $publicId = UuidV7::generate()->toString();
        $statement = $this->connection->prepare("INSERT INTO organization_affiliations (public_id,affiliation_code,workspace_id,organization_id,person_id,status,requested_by_account_id,request_expires_at,requested_at,activated_at,version,created_at,updated_at) VALUES (UUID_TO_BIN(:public_id),:code,:workspace_id,(SELECT id FROM organizations WHERE public_id=UUID_TO_BIN(:organization_id)),:person_id,'ACTIVE',:account_id,DATE_ADD(UTC_TIMESTAMP(6), INTERVAL 30 DAY),UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");
        $statement->execute(['public_id' => $publicId, 'code' => 'QMA-' . $suffix . bin2hex(random_bytes(7)), 'workspace_id' => $workspaceId, 'organization_id' => $organizationId, 'person_id' => $personId, 'account_id' => $accountId]);

        return $publicId;
    }

    /** @param list<array{workspace_id:int,affiliation_id:string,expected_version:int}> $payloads
     * @return list<bool>
     */
    private function runWorkers(array $payloads): array
    {
        $processes = [];
        $worker = dirname(__DIR__, 2) . '/Support/MySql/P3OrganizationAffiliationMutationWorker.php';
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
}
