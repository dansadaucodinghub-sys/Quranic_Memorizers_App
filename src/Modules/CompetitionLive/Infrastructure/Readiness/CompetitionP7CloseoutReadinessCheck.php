<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionLive\Infrastructure\Readiness;

use PDO;
use PDOStatement;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskMap;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Http\Routing\HttpMethod;
use Qmdb\Shared\Http\Routing\RouteCollection;
use Qmdb\Shared\Http\Routing\Security\ProductionRouteSecurityPolicyCatalog;
use Qmdb\Shared\Http\Routing\Security\RouteSecurityClassification;
use Qmdb\Shared\Http\Routing\Security\RouteSecurityVerifier;
use RuntimeException;

/**
 * Read-only P7 closeout composition. It validates the deployed schema and
 * registered runtime boundaries; it deliberately creates no competition data
 * and never changes publication, appeal, outbox, or scheduler state.
 */
final readonly class CompetitionP7CloseoutReadinessCheck
{
    /** @var list<string> */
    private const array REQUIRED_TABLES = [
        'competition_live_sessions', 'competition_live_participant_states',
        'competition_live_events', 'competition_live_projection_streams',
        'competition_live_projection_snapshots', 'competition_live_delivery_offsets',
        'competition_live_operations', 'competition_result_publications',
        'competition_result_publication_events', 'competition_result_publication_holds',
        'competition_result_packages', 'competition_result_publication_projections',
        'competition_result_publication_projection_heads', 'competition_appeal_review_assignments',
        'competition_appeal_reviewer_conflicts', 'competition_appeal_decisions',
        'competition_appeal_correction_authorizations', 'competition_p7_outbox_messages',
        'competition_p7_operations',
    ];

    /** @var list<string> */
    private const array REQUIRED_MIGRATIONS = [
        '20260912140000_create_competition_live_operations',
        '20260912141000_create_competition_live_projections',
        '20260912142000_add_competition_live_operation_sequence',
        '20260912143000_create_competition_result_publication',
        '20260912144000_create_competition_appeal_adjudication',
        '20260913100000_create_competition_p7_outbox',
        '20260913110000_create_competition_p7_operation_receipts',
        '20260913120000_create_competition_result_publication_projections',
    ];

    /** @var list<string> */
    private const array REQUIRED_TRIGGERS = [
        'trg_p7_live_events_no_update', 'trg_p7_live_events_no_delete',
        'trg_p7_projection_snapshots_no_update', 'trg_p7_projection_snapshots_no_delete',
        'trg_p7_publication_events_no_update', 'trg_p7_publication_events_no_delete',
        'trg_p7_result_packages_no_update', 'trg_p7_result_packages_no_delete',
        'trg_p7_result_projection_no_update', 'trg_p7_result_projection_no_delete',
        'trg_p7_appeal_decisions_no_update', 'trg_p7_appeal_decisions_no_delete',
    ];

    /** @var list<string> */
    private const array REQUIRED_TASKS = [
        'competition.live.project', 'competition.live.reconcile', 'competition.live.outbox.retry',
        'competition.result_publications.process', 'competition.result_publications.reconcile',
        'competition.appeals.process',
    ];

    /** @var list<string> */
    private const array PUBLIC_LIVE_ROUTES = [
        'competition.public.live', 'competition.public.live.snapshot', 'competition.public.live.stream',
    ];

    public function __construct(
        private DatabaseConnectionProvider $connections,
        private ScheduledTaskMap $scheduledTasks,
        private RouteCollection $routes,
        private RouteSecurityVerifier $routeSecurity,
    ) {
    }

    /** @return array{tables:int,migrations:int,triggers:int,private_routes:int,public_routes:int,scheduler_tasks:int} */
    public function verify(): array
    {
        $routeReport = $this->routeSecurity->verify($this->routes);
        if (!$routeReport->isValid()) {
            throw new RuntimeException('The production route-security catalog is invalid for P7 closeout.');
        }

        [$privateRoutes, $publicRoutes] = $this->verifyP7RouteBoundary();
        $this->verifyScheduledTasks();
        $pdo = $this->connections->connection();
        $this->verifyTables($pdo);
        $this->verifyMigrations($pdo);
        $this->verifyAuthorizationSeed($pdo);
        $this->verifyTriggers($pdo);
        $this->verifyDatabaseContracts($pdo);

        return [
            'tables' => count(self::REQUIRED_TABLES),
            'migrations' => count(self::REQUIRED_MIGRATIONS),
            'triggers' => count(self::REQUIRED_TRIGGERS),
            'private_routes' => $privateRoutes,
            'public_routes' => $publicRoutes,
            'scheduler_tasks' => count(self::REQUIRED_TASKS),
        ];
    }

    /** @return array{0:int,1:int} */
    private function verifyP7RouteBoundary(): array
    {
        $policies = (new ProductionRouteSecurityPolicyCatalog())->policies();
        $public = 0;
        $private = 0;
        foreach ($this->routes as $route) {
            $name = $route->name();
            if (in_array($name, self::PUBLIC_LIVE_ROUTES, true)) {
                $policy = $policies[$name] ?? null;
                if ($policy === null || $policy->classification !== RouteSecurityClassification::PUBLIC || $route->methods() !== [HttpMethod::GET]) {
                    throw new RuntimeException('A public P7 live route is not a GET-only public projection.');
                }
                ++$public;
                continue;
            }
            if (
                !str_starts_with($name, 'workspace.competition.live_')
                && !str_starts_with($name, 'workspace.competition.result_publication.')
                && !str_starts_with($name, 'workspace.competition.appeal_adjudication.')
            ) {
                continue;
            }
            $policy = $policies[$name] ?? null;
            if ($policy === null || !$policy->classification->requiresAuthentication() || !$policy->requiresTenantContext || !$policy->noStore) {
                throw new RuntimeException('A private P7 route lacks an authenticated tenant no-store boundary.');
            }
            foreach ($route->methods() as $method) {
                if ($method !== HttpMethod::GET && ($policy->csrfAction === null || !$policy->requiresIdempotency)) {
                    throw new RuntimeException('A P7 mutation route lacks CSRF or idempotency policy.');
                }
            }
            ++$private;
        }
        if ($public !== count(self::PUBLIC_LIVE_ROUTES)) {
            throw new RuntimeException('The complete public P7 live-projection route set is not registered.');
        }
        if ($private !== 56) {
            throw new RuntimeException('The complete private P7 lifecycle route set is not registered.');
        }

        return [$private, $public];
    }

    private function verifyScheduledTasks(): void
    {
        $registered = [];
        foreach ($this->scheduledTasks->tasks() as $task) {
            $registered[$task->id()->value()] = true;
        }
        foreach (self::REQUIRED_TASKS as $task) {
            if (!isset($registered[$task])) {
                throw new RuntimeException('A required P7 reconciliation task is not registered: ' . $task);
            }
        }
    }

    private function verifyTables(PDO $pdo): void
    {
        foreach (self::REQUIRED_TABLES as $table) {
            $this->requireCount($pdo, 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:value AND engine=\'InnoDB\'', 1, 'A required P7 InnoDB table is missing: ' . $table, [':value' => $table]);
        }
    }

    private function verifyMigrations(PDO $pdo): void
    {
        foreach (self::REQUIRED_MIGRATIONS as $migration) {
            $this->requireCount($pdo, 'SELECT COUNT(*) FROM qmdb_schema_migrations WHERE status=\'APPLIED\' AND migration_id=:value', 1, 'A required P7 migration is missing: ' . $migration, [':value' => $migration]);
        }
    }

    private function verifyAuthorizationSeed(PDO $pdo): void
    {
        $this->requireCount($pdo, "SELECT COUNT(*) FROM qmdb_schema_seeds WHERE status='APPLIED' AND seed_id='20260912145000_seed_competition_p7_authorization_catalog'", 1, 'The P7 authorization seed is missing.');
        $this->requireCount($pdo, "SELECT COUNT(*) FROM authorization_permissions WHERE owning_module='competition.live_operations' AND status='ACTIVE'", 7, 'The P7 permission catalog is incomplete.');
    }

    private function verifyTriggers(PDO $pdo): void
    {
        foreach (self::REQUIRED_TRIGGERS as $trigger) {
            $this->requireCount($pdo, 'SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND trigger_name=:value', 1, 'A P7 immutable-history trigger is missing: ' . $trigger, [':value' => $trigger]);
        }
    }

    private function verifyDatabaseContracts(PDO $pdo): void
    {
        foreach (
            [
            ['competition_p7_outbox_messages', 'uq_p7_outbox_message', 3],
            ['competition_p7_operations', 'uq_p7_operation_submission', 1],
            ['competition_result_publication_projections', 'uq_p7_publication_projection_version', 3],
            ['competition_result_publication_projection_heads', 'uq_p7_publication_projection_head', 2],
            ] as [$table, $index, $columns]
        ) {
            $this->requireCount($pdo, 'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=:table AND index_name=:index', $columns, 'A P7 unique concurrency contract is missing: ' . $index, [':table' => $table, ':index' => $index]);
        }
        $this->requireCount($pdo, "SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND constraint_type='CHECK' AND constraint_name IN ('ck_p7_outbox','ck_p7_operation','ck_p7_publication_projection','ck_p7_publication_projection_head')", 4, 'P7 bounded workload or projection integrity checks are incomplete.');
    }

    /** @param array<string,string> $parameters */
    private function requireCount(PDO $pdo, string $sql, int $expected, string $message, array $parameters = []): void
    {
        $statement = $pdo->prepare($sql);
        if (!$statement instanceof PDOStatement) {
            throw new RuntimeException('P7 closeout verification query could not be prepared.');
        }
        $statement->execute($parameters);
        if ((int) $statement->fetchColumn() !== $expected) {
            throw new RuntimeException($message);
        }
    }
}
