<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionLive\Interface\Console;

use Qmdb\Modules\CompetitionLive\Infrastructure\Persistence\CompetitionP7LiveMaintenanceService;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;

/** Closed operational CLI for P7 live projections; it never alters source events. */
final readonly class CompetitionP7LiveMaintenanceConsoleCommand implements ConsoleCommand
{
    public function __construct(private string $command, private string $operation, private CompetitionP7LiveMaintenanceService $maintenance)
    {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName($this->command);
    }

    public function description(): string
    {
        return 'Run bounded P7 ' . str_replace(':', ' ', $this->operation) . ' maintenance.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions(['limit']);
        $limit = $this->limit($input->scalar('limit'));
        $count = match ($this->operation) {
            'live-project' => $this->maintenance->project($limit),
            'live-reconcile' => $this->maintenance->reconcile($limit),
            'live-outbox-retry' => $this->maintenance->retryExpiredClaims($limit),
            default => throw new \LogicException('P7 live maintenance operation is invalid.'),
        };
        $output->writeln('Processed: ' . $count);

        return 0;
    }

    private function limit(?string $value): int
    {
        if ($value === null) {
            return 50;
        }
        if (preg_match('/^[1-9][0-9]{0,2}$/', $value) !== 1 || (int) $value > 500) {
            throw new \InvalidArgumentException('P7 maintenance limit is invalid.');
        }

        return (int) $value;
    }
}
