<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\MySql;

use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\Group;
use Qmdb\Modules\Organizations\Infrastructure\Persistence\MySqlOrganizationRegistryRepository;
use Qmdb\Modules\Organizations\Configuration\OrganizationsRegistryConfiguration;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Tests\Support\MySql\AuthorizationMySqlFixture;
use Qmdb\Tests\Support\MySql\MySqlIntegrationTestCase;

#[Group('OrganizationsRegistry')]
#[Group('TenantIsolation')]
#[Group('Concurrency')]
final class P3OrganizationsRegistryIntegrationTest extends MySqlIntegrationTestCase
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
            $this->fixture->rebuild();
        }
        parent::tearDown();
    }

    public function testRepositoryCannotReadAnOrganizationAcrossWorkspaceBoundaries(): void
    {
        [$account] = $this->fixture->account();
        [, , $workspaceA] = $this->fixture->workspace();
        [, , $workspaceB] = $this->fixture->workspace();
        $organizationId = $this->insertOrganization($workspaceA->workspaceInternalId(), $account, 'A');
        $repository = new MySqlOrganizationRegistryRepository($this->provider(), $this->registryConfiguration());

        self::assertNotNull($repository->organization($workspaceA, $organizationId));
        self::assertNull($repository->organization($workspaceB, $organizationId));
        self::assertSame([], $repository->list($workspaceB, 'tenant', 10));

        $statement = $this->connection->prepare(
            "INSERT INTO organization_names (public_id, workspace_id, organization_id, name_type, script_code, "
            . "display_name, search_name, status, version, effective_at, created_at, updated_at) "
            . "VALUES (UUID_TO_BIN(UUID()), :other_workspace, (SELECT id FROM organizations WHERE public_id = UUID_TO_BIN(:organization)), "
            . "'SHORT', 'LATIN', 'Cross tenant', 'cross tenant', 'ACTIVE', 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))",
        );
        $this->expectException(PDOException::class);
        $statement->execute(['other_workspace' => $workspaceB->workspaceInternalId(), 'organization' => $organizationId]);
    }

    public function testConcurrentOptimisticOrganizationUpdatesHaveOneWinner(): void
    {
        [$account] = $this->fixture->account();
        [, , $workspace] = $this->fixture->workspace();
        $organizationId = $this->insertOrganization($workspace->workspaceInternalId(), $account, 'C');
        $payload = json_encode([
            'workspace_id' => $workspace->workspaceInternalId(),
            'organization_id' => $organizationId,
            'expected_version' => 1,
        ], JSON_THROW_ON_ERROR);
        $results = $this->runWorkers([$payload, $payload]);
        sort($results);

        self::assertSame([false, true], $results);
        $statement = $this->connection->prepare('SELECT version FROM organizations WHERE public_id = UUID_TO_BIN(:id)');
        $statement->execute(['id' => $organizationId]);
        self::assertSame(2, (int) $statement->fetchColumn());
    }

    private function insertOrganization(int $workspaceId, int $accountId, string $suffix): string
    {
        $organizationId = UuidV7::generate()->toString();
        $statement = $this->connection->prepare(
            "INSERT INTO organizations (public_id, workspace_id, registry_code, status, created_by_account_id, version, created_at, updated_at) "
            . "VALUES (UUID_TO_BIN(:id), :workspace_id, :code, 'ACTIVE', :account_id, 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))",
        );
        $statement->execute([
            'id' => $organizationId,
            'workspace_id' => $workspaceId,
            'code' => 'QMO-TEST' . $suffix . bin2hex(random_bytes(6)),
            'account_id' => $accountId,
        ]);
        $organizationInternalId = (int) $this->connection->lastInsertId();
        $name = $this->connection->prepare(
            "INSERT INTO organization_names (public_id, workspace_id, organization_id, name_type, script_code, "
            . "display_name, search_name, status, version, effective_at, created_at, updated_at) "
            . "VALUES (UUID_TO_BIN(UUID()), :workspace_id, :organization_id, 'PRIMARY', 'LATIN', :name, :search_name, "
            . "'ACTIVE', 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))",
        );
        $name->execute([
            'workspace_id' => $workspaceId,
            'organization_id' => $organizationInternalId,
            'name' => 'Tenant Organization ' . $suffix,
            'search_name' => 'tenant organization ' . strtolower($suffix),
        ]);
        $jurisdiction = $this->connection->prepare(
            "INSERT INTO organization_jurisdictions (public_id, workspace_id, organization_id, jurisdiction_level, "
            . "country_id, level_one_area_id, level_two_area_id, source_type, status, version, effective_at, created_at, updated_at) "
            . "VALUES (UUID_TO_BIN(UUID()), :workspace_id, :organization_id, 'NOT_RECORDED', NULL, NULL, NULL, "
            . "'SELF_DECLARED', 'ACTIVE', 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))",
        );
        $jurisdiction->execute(['workspace_id' => $workspaceId, 'organization_id' => $organizationInternalId]);

        return $organizationId;
    }

    private function registryConfiguration(): OrganizationsRegistryConfiguration
    {
        return new OrganizationsRegistryConfiguration(8, 240, 320, 10, 5000, 900, 60, 900, 120);
    }

    /** @param list<string> $payloads
     * @return list<bool>
     */
    private function runWorkers(array $payloads): array
    {
        $processes = [];
        $worker = dirname(__DIR__, 2) . '/Support/MySql/P3OrganizationMutationWorker.php';
        foreach ($payloads as $payload) {
            $pipes = [];
            $process = proc_open([PHP_BINARY, $worker], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 3), null, ['bypass_shell' => true]);
            self::assertIsResource($process);
            fwrite($pipes[0], $payload);
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
