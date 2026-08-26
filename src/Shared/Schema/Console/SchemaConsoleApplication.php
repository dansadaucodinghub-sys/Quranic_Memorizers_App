<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Console;

use Closure;
use InvalidArgumentException;
use Qmdb\Bootstrap\Console\ConsoleResult;
use Qmdb\Bootstrap\Shared\ExitCode;
use Qmdb\Shared\Schema\Exception\SchemaException;
use Qmdb\Shared\Schema\Metadata\SchemaMetadataInstaller;
use Qmdb\Shared\Schema\Migration\MigrationPlan;
use Qmdb\Shared\Schema\Runner\MigrationRollbackService;
use Qmdb\Shared\Schema\Runner\MigrationRunner;
use Qmdb\Shared\Schema\Runner\SeedRunner;
use Qmdb\Shared\Schema\Status\SchemaStatusService;
use Throwable;

final readonly class SchemaConsoleApplication
{
    private const COMMANDS = [
        'db:schema:install',
        'db:schema:verify',
        'db:migrate:plan',
        'db:migrate',
        'db:migrate:status',
        'db:migrate:rollback',
        'db:seed',
        'db:seed:status',
    ];

    /** @param Closure(class-string): object $resolve */
    public function __construct(private Closure $resolve)
    {
    }

    public function supports(string $command): bool
    {
        return in_array($command, self::COMMANDS, true);
    }

    /** @return list<string> */
    public function commands(): array
    {
        return self::COMMANDS;
    }

    /** @param list<string> $arguments */
    public function run(array $arguments): ConsoleResult
    {
        $command = array_shift($arguments) ?? '';
        try {
            if (!$this->supports($command)) {
                throw new InvalidArgumentException('Unknown schema command.');
            }
            if ($command === 'db:migrate:rollback') {
                return $this->rollback($arguments);
            }
            if ($arguments !== []) {
                throw new InvalidArgumentException('This command does not accept options.');
            }

            return match ($command) {
                'db:schema:install' => $this->install(),
                'db:schema:verify' => $this->verify(),
                'db:migrate:plan', 'db:migrate:status' => $this->migrationPlan(),
                'db:migrate' => $this->migrate(),
                'db:seed' => $this->seed(),
                'db:seed:status' => $this->seedStatus(),
                default => throw new InvalidArgumentException('Unknown schema command.'),
            };
        } catch (InvalidArgumentException $exception) {
            return new ConsoleResult(
                ExitCode::INVALID_USAGE,
                standardError: $exception->getMessage() . "\n" . $this->usage($command),
            );
        } catch (SchemaException $exception) {
            return new ConsoleResult(
                ExitCode::FAILURE,
                standardError: $exception->safeCode() . ': ' . $exception->getMessage() . "\n",
            );
        } catch (Throwable $exception) {
            return new ConsoleResult(
                ExitCode::FAILURE,
                standardError: "SCHEMA_COMMAND_FAILED: Schema command failed safely.\n",
            );
        }
    }

    private function install(): ConsoleResult
    {
        $report = $this->service(SchemaMetadataInstaller::class)->install();

        return new ConsoleResult(
            $report->isReady() ? ExitCode::SUCCESS : ExitCode::FAILURE,
            standardOutput: $report->isReady() ? "Schema metadata installed and verified.\n" : '',
            standardError: $report->isReady() ? '' : "Schema metadata is not ready.\n",
        );
    }

    private function verify(): ConsoleResult
    {
        $status = $this->service(SchemaStatusService::class);
        $metadata = $status->verify();
        if (!$metadata->isReady()) {
            return new ConsoleResult(ExitCode::FAILURE, standardError: $metadata->safeCode . "\n");
        }
        $migrationPlan = $status->migrationPlan();
        $seedPlan = $status->seedPlan();
        $clean = !$migrationPlan->isBlocked() && $migrationPlan->pending() === [] && !$seedPlan->blocked;

        return new ConsoleResult(
            $clean ? ExitCode::SUCCESS : ExitCode::FAILURE,
            standardOutput: $clean ? "Schema ledger verified.\n" : '',
            standardError: $clean ? '' : "SCHEMA_STATE_NOT_CLEAN\n",
        );
    }

    private function migrationPlan(): ConsoleResult
    {
        $plan = $this->service(SchemaStatusService::class)->migrationPlan();
        if ($plan->items === []) {
            return new ConsoleResult(ExitCode::SUCCESS, "No pending migrations.\n");
        }
        $lines = array_map(
            static fn ($item): string => $item->id . ' | ' . $item->status->value . ' | ' . $item->description,
            $plan->items,
        );

        return new ConsoleResult(
            $plan->isBlocked() ? ExitCode::FAILURE : ExitCode::SUCCESS,
            implode("\n", $lines) . "\n",
        );
    }

    private function migrate(): ConsoleResult
    {
        $summary = $this->service(MigrationRunner::class)->run();

        return new ConsoleResult(
            ExitCode::SUCCESS,
            $summary->noOp
                ? "No pending migrations.\n"
                : 'Applied migrations: ' . implode(', ', $summary->processedIds) . "\n",
        );
    }

    private function seed(): ConsoleResult
    {
        $summary = $this->service(SeedRunner::class)->run();

        return new ConsoleResult(
            ExitCode::SUCCESS,
            $summary->noOp ? "No pending seeds.\n" : 'Applied seeds: ' . implode(', ', $summary->processedIds) . "\n",
        );
    }

    private function seedStatus(): ConsoleResult
    {
        $plan = $this->service(SchemaStatusService::class)->seedPlan();
        if ($plan->pending === [] && !$plan->blocked) {
            return new ConsoleResult(ExitCode::SUCCESS, "No pending seeds.\n");
        }
        $lines = array_map(static fn ($seed): string => $seed->id()->value() . ' | PENDING', $plan->pending);
        if ($plan->blocked) {
            $lines[] = 'Seed execution is blocked by ledger state.';
        }

        return new ConsoleResult(
            $plan->blocked ? ExitCode::FAILURE : ExitCode::SUCCESS,
            implode("\n", $lines) . "\n",
        );
    }

    /** @param list<string> $arguments */
    private function rollback(array $arguments): ConsoleResult
    {
        $options = [];
        foreach ($arguments as $argument) {
            if (preg_match('/\A--(migration|confirm)=([^=]+)\z/', $argument, $matches) !== 1) {
                throw new InvalidArgumentException('Rollback options are malformed.');
            }
            if (isset($options[$matches[1]])) {
                throw new InvalidArgumentException('Rollback option was repeated.');
            }
            $options[$matches[1]] = $matches[2];
        }
        if (!isset($options['migration'], $options['confirm'])) {
            throw new InvalidArgumentException('Rollback requires migration and confirmation options.');
        }
        $summary = $this->service(MigrationRollbackService::class)->rollback(
            $options['migration'],
            $options['confirm'],
        );

        return new ConsoleResult(
            ExitCode::SUCCESS,
            'Rolled back migration: ' . implode(', ', $summary->processedIds) . "\n",
        );
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    private function service(string $id): object
    {
        $service = ($this->resolve)($id);
        if (!$service instanceof $id) {
            throw new SchemaException('SCHEMA_SERVICE_INVALID', 'Schema service is invalid.');
        }

        return $service;
    }

    private function usage(string $command): string
    {
        if ($command === 'db:migrate:rollback') {
            return "Usage: php bin/console db:migrate:rollback --migration=<id> --confirm=<id>\n";
        }

        return "Usage: php bin/console <schema-command>\n";
    }
}
