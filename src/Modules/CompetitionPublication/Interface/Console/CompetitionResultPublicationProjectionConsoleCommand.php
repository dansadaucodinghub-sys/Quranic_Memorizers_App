<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionPublication\Interface\Console;

use Qmdb\Modules\CompetitionPublication\Infrastructure\Persistence\CompetitionResultPublicationProjectionService;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;

/** Bounded P7 publication-projection operations; verification never repairs. */
final readonly class CompetitionResultPublicationProjectionConsoleCommand implements ConsoleCommand
{
    public function __construct(private string $command, private string $operation, private CompetitionResultPublicationProjectionService $projections)
    {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName($this->command);
    }

    public function description(): string
    {
        return 'Run bounded P7 result-publication projection ' . $this->operation . '.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions(['dry-run', 'limit']);
        $dryRun = $input->requireFlag('dry-run');
        $limit = $this->limit($input->scalar('limit'));
        $count = match ($this->operation) {
            'process' => $this->projections->process($limit, $dryRun),
            'rebuild' => $this->projections->rebuild($limit, $dryRun),
            'reconcile', 'verify' => $this->projections->reconcile($limit),
            default => throw new \LogicException('Publication projection console operation is invalid.'),
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
            throw new \InvalidArgumentException('Publication projection limit is invalid.');
        }

        return (int) $value;
    }
}
