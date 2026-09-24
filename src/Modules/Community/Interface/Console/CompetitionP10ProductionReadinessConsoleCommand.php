<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Interface\Console;

use Qmdb\Modules\Community\Infrastructure\Persistence\MySqlCommunityFoundationVerificationRepository;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskMap;
use Qmdb\Shared\Configuration\ApplicationConfiguration;
use Qmdb\Shared\Configuration\ConfigurationSource;
use Qmdb\Shared\Configuration\EnvironmentVariables;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;
use Throwable;

/** Read-only predeployment contract; strict mode rejects test providers and developer configuration. */
final readonly class CompetitionP10ProductionReadinessConsoleCommand implements ConsoleCommand
{
    public function __construct(
        private MySqlCommunityFoundationVerificationRepository $inventory,
        private ScheduledTaskMap $tasks,
        private ApplicationConfiguration $application,
        private EnvironmentVariables $environment,
    ) {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('competition:p10:production-readiness:verify');
    }

    public function description(): string
    {
        return 'Verify the P10 deployment contract and reject unsafe production-like configuration.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions(['production-like']);
        $strict = $input->requireFlag('production-like');
        try {
            $inventory = $this->inventory->verify();
            $this->verifyScheduler();
            $this->verifyConfiguration($strict);
            $scope = $strict ? 'production-like' : 'predeployment';
            $output->write("Competition P10 {$scope} readiness: PASS\n"
                . "Required tables: {$inventory['tables']}\n"
                . "Required migrations: {$inventory['migrations']}\n"
                . "Notification scheduler: community.notifications.deliver\n");
            return 0;
        } catch (Throwable $error) {
            $output->write("Competition P10 production readiness: FAIL\n{$error->getMessage()}\n");
            return 1;
        }
    }

    private function verifyScheduler(): void
    {
        foreach ($this->tasks->tasks() as $task) {
            if ($task->id()->value() === 'community.notifications.deliver') {
                return;
            }
        }
        throw new \RuntimeException('Required P10 notification scheduler is missing.');
    }

    private function verifyConfiguration(bool $strict): void
    {
        if ($this->application->debugEnabled()) {
            throw new \RuntimeException('Debug mode is enabled.');
        }
        if (!$strict) {
            return;
        }
        if (
            !$this->application->isProductionLike()
            || $this->application->source() !== ConfigurationSource::PROCESS
        ) {
            throw new \RuntimeException('Strict readiness requires externally injected staging or production configuration.');
        }
        $mailer = $this->environment->optionalString('MAILER_DSN');
        if ($mailer === null || $mailer === '' || str_starts_with($mailer, 'null:')) {
            throw new \RuntimeException('A non-test notification provider is required.');
        }
        foreach (['QMDB_MEDIA_SCANNER_BINARY', 'QMDB_MEDIA_FFMPEG_BINARY', 'QMDB_MEDIA_FFPROBE_BINARY'] as $name) {
            $binary = $this->environment->optionalString($name);
            if ($binary === null || !is_file($binary)) {
                throw new \RuntimeException('Required P9 production media provider is unavailable: ' . $name);
            }
        }
    }
}
