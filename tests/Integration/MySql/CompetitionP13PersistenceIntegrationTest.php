<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\MySql;

use PDO;
use PHPUnit\Framework\Attributes\Group;
use Qmdb\Bootstrap\ApplicationFactory;
use Qmdb\Modules\PilotOfflineRollout\Application\P13RouteRuntimeCatalog;
use Qmdb\Modules\PilotOfflineRollout\Infrastructure\Persistence\MySqlPilotOfflineRolloutRepository;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\DependencyInjection\CompiledContainer;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Tests\Support\MySql\MySqlIntegrationTestCase;

#[Group('P13')]
#[Group('P13Persistence')]
final class CompetitionP13PersistenceIntegrationTest extends MySqlIntegrationTestCase
{
    public function testP13MutationFamiliesPersistUnderRealOracleMySql(): void
    {
        $container = $this->container();
        $connections = $container->get(DatabaseConnectionProvider::class);
        $repository = $container->get(MySqlPilotOfflineRolloutRepository::class);
        self::assertInstanceOf(DatabaseConnectionProvider::class, $connections);
        self::assertInstanceOf(MySqlPilotOfflineRolloutRepository::class, $repository);
        $database = $connections->connection();
        $actor = $this->createActor($database);
        $suffix = strtoupper(bin2hex(random_bytes(5)));

        try {
            $pilot = $repository->createPilot(
                'PILOT_' . $suffix,
                'P13 persistence evidence',
                'VENUE',
                '2027-01-01 00:00:00.000000',
                '2027-01-31 00:00:00.000000',
                $actor,
            );
            $repository->transitionPilot($pilot, 'READINESS_REVIEW', 1, $actor, 'TEST_READINESS');
            $repository->transitionPilot($pilot, 'APPROVED', 2, $actor, 'TEST_APPROVAL');
            $pilotRow = $this->row($database, 'SELECT status_code,version FROM pilot_programs WHERE public_id=:public', $pilot);
            self::assertSame('APPROVED', $pilotRow['status_code']);
            self::assertIsInt($pilotRow['version']);
            self::assertSame(3, $pilotRow['version']);
            self::assertSame(2, $this->databaseCount($database, 'SELECT COUNT(*) FROM pilot_events WHERE pilot_program_id=(SELECT id FROM pilot_programs WHERE public_id=:public)', $pilot));

            $rollout = $repository->createRollout('ROLLOUT_' . $suffix, 'P13 rollout evidence', 'NATIONAL', 10, $actor);
            $wave = $repository->createWave(
                $rollout,
                'Evidence wave',
                '2027-02-01 00:00:00.000000',
                '2027-02-28 00:00:00.000000',
                $actor,
            );
            $waveRow = $this->row($database, 'SELECT status_code,version FROM rollout_waves WHERE public_id=:public', $wave);
            self::assertSame('PLANNED', $waveRow['status_code']);
            self::assertIsInt($waveRow['version']);
            self::assertSame(1, $waveRow['version']);

            $submission = UuidV7::generate()->toString();
            $hash = hash('sha256', 'p13-replay-' . $suffix);
            self::assertTrue($repository->claimOperation('PLATFORM', 'p13-evidence', 'platform.pilots.create', $submission, $hash));
            $repository->completeOperation('PLATFORM', 'p13-evidence', 'platform.pilots.create', $submission, true, $pilot);
            self::assertFalse($repository->claimOperation('PLATFORM', 'p13-evidence', 'platform.pilots.create', $submission, $hash));
            self::assertFalse($repository->claimOperation('PLATFORM', 'p13-evidence', 'platform.pilots.create', $submission, hash('sha256', 'mismatch-' . $suffix)));
            self::assertSame(1, $this->databaseCount($database, "SELECT COUNT(*) FROM p13_operation_receipts WHERE submission_id='" . $submission . "'"));

            self::assertCount(40, P13RouteRuntimeCatalog::MUTATIONS);
            self::assertSame('InnoDB', $this->scalar($database, "SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='pilot_programs'"));
            self::assertSame(8, $this->databaseCount($database, "SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND trigger_name LIKE 'trg_p13_%_no_%'"));
        } finally {
            $this->cleanup($database, $actor, $suffix);
        }
    }

    public function testAuthoritativeMutationAndReceiptRollbackTogether(): void
    {
        $container = $this->container();
        $connections = $container->get(DatabaseConnectionProvider::class);
        $transactions = $container->get(TransactionManager::class);
        $repository = $container->get(MySqlPilotOfflineRolloutRepository::class);
        self::assertInstanceOf(DatabaseConnectionProvider::class, $connections);
        self::assertInstanceOf(TransactionManager::class, $transactions);
        self::assertInstanceOf(MySqlPilotOfflineRolloutRepository::class, $repository);
        $database = $connections->connection();
        $actor = $this->createActor($database);
        $suffix = strtoupper(bin2hex(random_bytes(5)));
        $submission = UuidV7::generate()->toString();

        try {
            try {
                $transactions->transactional(function () use ($repository, $actor, $suffix, $submission): void {
                    $pilot = $repository->createPilot('ROLLBACK_' . $suffix, 'Rollback evidence', 'VENUE', '2027-03-01 00:00:00.000000', '2027-03-31 00:00:00.000000', $actor);
                    self::assertTrue($repository->claimOperation('PLATFORM', 'rollback-evidence', 'platform.pilots.create', $submission, hash('sha256', $suffix)));
                    $repository->completeOperation('PLATFORM', 'rollback-evidence', 'platform.pilots.create', $submission, true, $pilot);
                    throw new \RuntimeException('fault-before-commit');
                });
            } catch (\RuntimeException $error) {
                self::assertSame('fault-before-commit', $error->getMessage());
            }
            self::assertSame(0, $this->databaseCount($database, "SELECT COUNT(*) FROM pilot_programs WHERE program_code='ROLLBACK_" . $suffix . "'"));
            self::assertSame(0, $this->databaseCount($database, "SELECT COUNT(*) FROM p13_operation_receipts WHERE submission_id='" . $submission . "'"));

            $pilot = $repository->createPilot('RETRY_' . $suffix, 'Retry evidence', 'VENUE', '2027-03-01 00:00:00.000000', '2027-03-31 00:00:00.000000', $actor);
            self::assertTrue($repository->claimOperation('PLATFORM', 'rollback-evidence', 'platform.pilots.create', $submission, hash('sha256', $suffix)));
            $repository->completeOperation('PLATFORM', 'rollback-evidence', 'platform.pilots.create', $submission, true, $pilot);
            self::assertSame(1, $this->databaseCount($database, "SELECT COUNT(*) FROM pilot_programs WHERE program_code='RETRY_" . $suffix . "'"));
        } finally {
            $this->cleanup($database, $actor, $suffix);
        }
    }

    public function testScopedPilotDevicePackageAndRolloutFamiliesPersistAtomically(): void
    {
        $container = $this->container();
        $connections = $container->get(DatabaseConnectionProvider::class);
        $transactions = $container->get(TransactionManager::class);
        $repository = $container->get(MySqlPilotOfflineRolloutRepository::class);
        self::assertInstanceOf(DatabaseConnectionProvider::class, $connections);
        self::assertInstanceOf(TransactionManager::class, $transactions);
        self::assertInstanceOf(MySqlPilotOfflineRolloutRepository::class, $repository);
        $database = $connections->connection();
        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $workspaceCode = 'p13-' . strtolower($suffix);

        try {
            $transactions->transactional(function () use ($database, $repository, $suffix, $workspaceCode): void {
                $actor = $this->createActor($database);
                $workspace = UuidV7::generate();
                $this->execute($database, "INSERT INTO workspaces (public_id,workspace_code,name,status_code,version,created_at,updated_at) VALUES (:public,:code,'P13 evidence workspace','ACTIVE',1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))", [':public' => $workspace->toBinary(), ':code' => $workspaceCode]);
                $workspaceId = (int) $database->lastInsertId();
                $this->execute($database, "INSERT INTO organizations (public_id,workspace_id,registry_code,status,created_by_account_id,version,created_at,updated_at,retired_at) VALUES (:public,:workspace,:code,'ACTIVE',:actor,1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL)", [':public' => UuidV7::generate()->toBinary(), ':workspace' => $workspaceId, ':code' => 'P13' . $suffix, ':actor' => $actor]);
                $organizationId = (int) $database->lastInsertId();
                $this->execute($database, "INSERT INTO competition_programs (public_id,workspace_id,primary_organizer_organization_id,program_code,slug,name_en,status,version,created_by_account_id,created_at,updated_at,activated_at,retired_at) VALUES (:public,:workspace,:organization,:code,:slug,'P13 evidence program','DRAFT',1,:actor,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL,NULL)", [':public' => UuidV7::generate()->toBinary(), ':workspace' => $workspaceId, ':organization' => $organizationId, ':code' => 'PROGRAM_' . $suffix, ':slug' => 'program-' . strtolower($suffix), ':actor' => $actor]);
                $programId = (int) $database->lastInsertId();
                $edition = UuidV7::generate();
                $this->execute($database, "INSERT INTO competition_editions (public_id,workspace_id,program_id,edition_code,slug,title_en,scope_type,other_scope_label_en,timezone_name,eligibility_reference_date,competition_starts_at,competition_ends_at,status,public_visibility,version,created_by_account_id,created_at,updated_at) VALUES (:public,:workspace,:program,:code,:slug,'P13 evidence edition','OTHER','P13 isolated fixture','UTC','2027-01-01','2027-01-01 00:00:00.000000','2027-12-31 00:00:00.000000','DRAFT','PRIVATE',1,:actor,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))", [':public' => $edition->toBinary(), ':workspace' => $workspaceId, ':program' => $programId, ':code' => 'EDITION_' . $suffix, ':slug' => 'edition-' . strtolower($suffix), ':actor' => $actor]);
                $editionId = (int) $database->lastInsertId();
                $venue = UuidV7::generate();
                $this->execute($database, "INSERT INTO competition_venues (public_id,workspace_id,edition_id,venue_type,venue_name,version,created_at,updated_at) VALUES (:public,:workspace,:edition,'PHYSICAL','P13 evidence venue',1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))", [':public' => $venue->toBinary(), ':workspace' => $workspaceId, ':edition' => $editionId]);

                $pilot = $repository->createPilot('SITE_' . $suffix, 'P13 scoped pilot', 'VENUE', '2027-04-01 00:00:00.000000', '2027-04-30 00:00:00.000000', $actor);
                $site = $repository->assignPilotSite($pilot, $workspace, $venue, $edition, $actor, 1, $actor);
                $repository->evaluatePilotReadiness($pilot, $site, 1, 'DEVICE_READY', true, 'PASS', 'isolated-evidence', '2027-05-01 00:00:00.000000', $actor);
                $incident = $repository->createPilotIncident($pilot, $site, 2, 'OFFLINE_SYNC', 'LOW', 'Deterministic isolated incident.', $actor);
                self::assertSame(1, $this->databaseCount($database, 'SELECT COUNT(*) FROM pilot_incidents WHERE public_id=:public', $incident));

                $device = $repository->registerDevice($workspaceId, $venue, 'DEVICE_' . $suffix, 'P13 evidence device', 'TABLET', 'TEST', random_bytes(32), $actor);
                $repository->transitionDevice($workspaceId, $device, 'ACTIVE', 1, $actor);
                $repository->rotateDeviceKey($workspaceId, $device, 2, 'KEY_' . $suffix, random_bytes(32), '2027-12-31 00:00:00.000000', $actor);
                self::assertSame(1, $this->databaseCount($database, "SELECT COUNT(*) FROM offline_device_keys WHERE workspace_id=" . $workspaceId . " AND status_code='ACTIVE'"));

                $entities = [['type' => 'VENUE_ASSIGNMENT', 'id' => UuidV7::generate()->toString(), 'version' => 1, 'payload' => ['venue' => $venue->toString()]]];
                $firstPackage = $repository->preparePackage($workspaceId, $device, $edition, $entities, $actor);
                $secondPackage = $repository->preparePackage($workspaceId, $device, $edition, $entities, $actor);
                $repository->supersedePackage($workspaceId, $firstPackage, $secondPackage, 1, $actor);
                $repository->revokePackage($workspaceId, $secondPackage, 1, $actor);
                self::assertSame(1, $this->databaseCount($database, 'SELECT COUNT(*) FROM offline_assignment_packages WHERE public_id=:public AND status_code="SUPERSEDED"', $firstPackage));
                self::assertSame(1, $this->databaseCount($database, 'SELECT COUNT(*) FROM offline_assignment_packages WHERE public_id=:public AND status_code="REVOKED"', $secondPackage));

                $rollout = $repository->createRollout('WAVE_' . $suffix, 'P13 scoped rollout', 'NATIONAL', 2, $actor);
                $wave = $repository->createWave($rollout, 'P13 evidence wave', '2027-06-01 00:00:00.000000', '2027-06-30 00:00:00.000000', $actor);
                $repository->assignRolloutWave($wave, 1, $workspace, $venue, null, null, $actor);
                $readiness = hash('sha256', 'readiness-' . $suffix);
                $repository->evaluateRolloutWave($wave, 2, $readiness, $actor);
                $repository->decideRolloutWave($wave, 3, 'GO', $readiness, hash('sha256', 'health-' . $suffix), 'TEST_GO', $actor);
                $repository->transitionRolloutWave($wave, 4, 'ACTIVE', 'TEST_ACTIVATION', $actor);
                $waveRow = $this->row($database, 'SELECT status_code,version FROM rollout_waves WHERE public_id=:public', $wave);
                self::assertSame('ACTIVE', $waveRow['status_code']);
                self::assertIsInt($waveRow['version']);
                self::assertSame(5, $waveRow['version']);

                throw new \RuntimeException('P13_SCOPED_FIXTURE_ROLLBACK');
            });
        } catch (\RuntimeException $error) {
            self::assertSame('P13_SCOPED_FIXTURE_ROLLBACK', $error->getMessage());
        }
        $statement = $database->prepare('SELECT COUNT(*) FROM workspaces WHERE workspace_code=:code');
        self::assertInstanceOf(\PDOStatement::class, $statement);
        $statement->execute([':code' => $workspaceCode]);
        self::assertSame(0, (int) $statement->fetchColumn());
    }

    private function container(): CompiledContainer
    {
        $factory = ApplicationFactory::fromCurrentProcess();
        $method = new \ReflectionMethod($factory, 'compose');
        $container = $method->invoke($factory, null, null);
        self::assertInstanceOf(CompiledContainer::class, $container);

        return $container;
    }

    private function createActor(PDO $database): int
    {
        $statement = $database->prepare("INSERT INTO user_accounts (public_id,account_status,preferred_locale,preferred_time_zone,version,created_at,updated_at) VALUES (:public,'ACTIVE','en','UTC',1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");
        self::assertInstanceOf(\PDOStatement::class, $statement);
        $statement->execute([':public' => UuidV7::generate()->toBinary()]);

        return (int) $database->lastInsertId();
    }

    /** @param array<string,int|string> $parameters */
    private function execute(PDO $database, string $sql, array $parameters): void
    {
        $statement = $database->prepare($sql);
        self::assertInstanceOf(\PDOStatement::class, $statement);
        $statement->execute($parameters);
    }

    /** @return array<string,mixed> */
    private function row(PDO $database, string $sql, UuidV7 $publicId): array
    {
        $statement = $database->prepare($sql);
        self::assertInstanceOf(\PDOStatement::class, $statement);
        $statement->execute([':public' => $publicId->toBinary()]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);

        $result = [];
        foreach ($row as $key => $value) {
            self::assertIsString($key);
            $result[$key] = $value;
        }

        return $result;
    }

    private function databaseCount(PDO $database, string $sql, ?UuidV7 $publicId = null): int
    {
        $statement = $database->prepare($sql);
        self::assertInstanceOf(\PDOStatement::class, $statement);
        $statement->execute($publicId === null ? [] : [':public' => $publicId->toBinary()]);

        return (int) $statement->fetchColumn();
    }

    private function scalar(PDO $database, string $sql): string
    {
        $statement = $database->query($sql);
        self::assertInstanceOf(\PDOStatement::class, $statement);
        $value = $statement->fetchColumn();
        self::assertIsString($value);

        return $value;
    }

    private function cleanup(PDO $database, int $actor, string $suffix): void
    {
        $database->exec("DELETE FROM p13_operation_receipts WHERE operation_code='platform.pilots.create' AND scope_reference_hash IN (UNHEX(SHA2('p13-evidence',256)),UNHEX(SHA2('rollback-evidence',256)))");
        $database->exec("DELETE FROM rollout_events WHERE rollout_plan_id IN (SELECT id FROM rollout_plans WHERE rollout_code='ROLLOUT_" . $suffix . "')");
        $database->exec("DELETE FROM rollout_waves WHERE rollout_plan_id IN (SELECT id FROM rollout_plans WHERE rollout_code='ROLLOUT_" . $suffix . "')");
        $database->exec("DELETE FROM rollout_plans WHERE rollout_code='ROLLOUT_" . $suffix . "'");
        $database->exec("DELETE FROM pilot_events WHERE pilot_program_id IN (SELECT id FROM pilot_programs WHERE program_code IN ('PILOT_" . $suffix . "','RETRY_" . $suffix . "','ROLLBACK_" . $suffix . "'))");
        $database->exec("DELETE FROM pilot_programs WHERE program_code IN ('PILOT_" . $suffix . "','RETRY_" . $suffix . "','ROLLBACK_" . $suffix . "')");
        $statement = $database->prepare('DELETE FROM user_accounts WHERE id=:actor');
        self::assertInstanceOf(\PDOStatement::class, $statement);
        $statement->execute([':actor' => $actor]);
    }
}
