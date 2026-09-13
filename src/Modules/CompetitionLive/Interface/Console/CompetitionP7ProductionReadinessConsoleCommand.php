<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionLive\Interface\Console;

use PDO;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskMap;
use Qmdb\Shared\Configuration\ApplicationConfiguration;
use Qmdb\Shared\Configuration\ConfigurationSource;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use RuntimeException;
use Throwable;

/**
 * Read-only predeployment verifier.  Strict mode intentionally fails closed
 * unless the process is running under externally injected production-like
 * configuration; it never treats a developer .env as a deployment profile.
 */
final readonly class CompetitionP7ProductionReadinessConsoleCommand implements ConsoleCommand
{
    /** @var list<string> */
    private const REQUIRED_TASKS = [
        'competition.live.project',
        'competition.live.reconcile',
        'competition.live.outbox.retry',
        'competition.result_publications.process',
        'competition.result_publications.reconcile',
        'competition.appeals.process',
    ];

    public function __construct(
        private ApplicationConfiguration $application,
        private DatabaseConnectionProvider $connections,
        private ScheduledTaskMap $scheduledTasks,
    ) {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('competition:p7:production-readiness:verify');
    }

    public function description(): string
    {
        return 'Verify P7 predeployment state and, in strict mode, production-like configuration.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions(['production-like']);
        $strict = $input->requireFlag('production-like');

        try {
            $this->verifyConfiguration($strict);
            $this->verifyP7Schema();
            $this->verifyScheduledTasks();

            $profile = $this->application->environment()->toSafeString();
            $scope = $strict ? 'production-like' : 'predeployment';
            $output->write(
                "Competition P7 {$scope} readiness: PASS\n",
            );
            $output->write("Configuration profile: {$profile}\n");
            $output->write('Required scheduler tasks: ' . count(self::REQUIRED_TASKS) . "\n");

            return 0;
        } catch (Throwable $error) {
            $output->write("Competition P7 production readiness: FAIL\n{$error->getMessage()}\n");

            return 1;
        }
    }

    private function verifyP7Schema(): void
    {
        $pdo = $this->connections->connection();
        $this->requireCount(
            $pdo,
            'SELECT COUNT(*) FROM information_schema.tables '
            . 'WHERE table_schema = DATABASE() AND table_name IN ('
            . "'competition_live_sessions','competition_live_events',"
            . "'competition_live_projection_snapshots','competition_result_publications',"
            . "'competition_result_packages','competition_result_publication_projections',"
            . "'competition_result_publication_projection_heads','competition_appeal_decisions',"
            . "'competition_p7_outbox_messages')",
            9,
            'Required P7 runtime tables are incomplete.',
        );
        $this->requireCount(
            $pdo,
            "SELECT COUNT(*) FROM qmdb_schema_migrations WHERE status = 'APPLIED' "
            . 'AND migration_id IN ('
            . "'20260912140000_create_competition_live_operations',"
            . "'20260912141000_create_competition_live_projections',"
            . "'20260912142000_add_competition_live_operation_sequence',"
            . "'20260912143000_create_competition_result_publication',"
            . "'20260912144000_create_competition_appeal_adjudication',"
            . "'20260913100000_create_competition_p7_outbox',"
            . "'20260913110000_create_competition_p7_operation_receipts',"
            . "'20260913120000_create_competition_result_publication_projections')",
            8,
            'Required P7 migrations are incomplete.',
        );
        $this->requireCount(
            $pdo,
            "SELECT COUNT(*) FROM qmdb_schema_seeds WHERE status = 'APPLIED' "
            . "AND seed_id = '20260912145000_seed_competition_p7_authorization_catalog'",
            1,
            'Required P7 authorization seed is not applied.',
        );
    }

    private function verifyScheduledTasks(): void
    {
        $available = [];
        foreach ($this->scheduledTasks->tasks() as $task) {
            $available[$task->id()->value()] = true;
        }
        foreach (self::REQUIRED_TASKS as $task) {
            if (!isset($available[$task])) {
                throw new RuntimeException('Required P7 scheduler task is not registered: ' . $task);
            }
        }
    }

    private function verifyConfiguration(bool $strict): void
    {
        if ($this->application->debugEnabled()) {
            throw new RuntimeException('Debug mode is enabled.');
        }
        if (!$strict) {
            return;
        }
        if (!$this->application->isProductionLike()) {
            throw new RuntimeException('Strict verification requires staging or production APP_ENV.');
        }
        if ($this->application->source() !== ConfigurationSource::PROCESS) {
            throw new RuntimeException(
                'Production-like configuration must be externally injected, not loaded from .env.',
            );
        }
    }

    private function requireCount(PDO $pdo, string $sql, int $expected, string $message): void
    {
        $statement = $pdo->prepare($sql);
        if ($statement === false) {
            throw new RuntimeException('P7 readiness query could not be prepared.');
        }
        $statement->execute();
        if ((int) $statement->fetchColumn() !== $expected) {
            throw new RuntimeException($message);
        }
    }
}
