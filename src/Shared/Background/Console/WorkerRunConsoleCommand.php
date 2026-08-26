<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Console;

use InvalidArgumentException;
use Qmdb\Shared\Background\Configuration\BackgroundExecutionConfiguration;
use Qmdb\Shared\Background\Worker\BackgroundWorker;
use Qmdb\Shared\Background\Worker\BackgroundWorkerOptions;
use Qmdb\Shared\Background\Worker\WorkerSignalController;
use Qmdb\Shared\Configuration\ApplicationEnvironment;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;

final readonly class WorkerRunConsoleCommand implements ConsoleCommand
{
    private const OPTIONS = [
        'once',
        'max-jobs',
        'max-runtime-seconds',
        'idle-sleep-ms',
        'max-memory-mb',
    ];

    public function __construct(
        private BackgroundWorker $worker,
        private BackgroundExecutionConfiguration $configuration,
        private ApplicationEnvironment $environment,
        private WorkerSignalController $signals,
    ) {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('worker:run');
    }

    public function description(): string
    {
        return 'Run one bounded background worker process.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        try {
            $input->assertOnlyOptions(self::OPTIONS);
            $runOnce = $input->requireFlag('once');
            if (
                !$runOnce
                && $this->environment->isProductionLike()
                && $this->configuration->requirePcntlInProduction()
                && !$this->signals->isSupported()
            ) {
                $output->errorLine('Worker signal support is required in this environment.');

                return 1;
            }
            $options = new BackgroundWorkerOptions(
                $runOnce,
                $this->positive($input, 'max-jobs', $this->configuration->maximumJobs()),
                $this->positive(
                    $input,
                    'max-runtime-seconds',
                    $this->configuration->maximumRuntimeSeconds(),
                ),
                $this->nonNegative(
                    $input,
                    'idle-sleep-ms',
                    $this->configuration->idleSleepMilliseconds(),
                ),
                $this->positive(
                    $input,
                    'max-memory-mb',
                    $this->configuration->maximumMemoryMegabytes(),
                ),
            );
            $result = $this->worker->run($options);
            $output->writeln('Worker stopped: ' . $result->stopReason()->value);
            $output->writeln('Processed: ' . $result->processedCount());
            $output->writeln('Failed: ' . $result->failedCount());

            return $result->isSuccessful() ? 0 : 1;
        } catch (InvalidArgumentException $exception) {
            $output->errorLine($exception->getMessage());

            return 64;
        }
    }

    private function positive(ConsoleInput $input, string $name, int $default): int
    {
        $value = $this->integer($input, $name, $default);
        if ($value < 1) {
            throw new InvalidArgumentException('Worker option must be a positive integer.');
        }

        return $value;
    }

    private function nonNegative(ConsoleInput $input, string $name, int $default): int
    {
        $value = $this->integer($input, $name, $default);
        if ($value < 0) {
            throw new InvalidArgumentException('Worker option must be a non-negative integer.');
        }

        return $value;
    }

    private function integer(ConsoleInput $input, string $name, int $default): int
    {
        $raw = $input->scalar($name);
        if ($raw === null) {
            return $default;
        }
        $value = filter_var($raw, FILTER_VALIDATE_INT);
        if (!is_int($value)) {
            throw new InvalidArgumentException('Worker option must be an integer.');
        }

        return $value;
    }
}
