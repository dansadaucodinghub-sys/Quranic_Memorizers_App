<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Interface\Console;

use Qmdb\Modules\SecurityAudit\Infrastructure\Persistence\SecurityAuditCheckpointService;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;

final readonly class SecurityAuditCheckpointConsoleCommand implements ConsoleCommand
{
    public function __construct(private SecurityAuditCheckpointService $checkpoints)
    {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('security:audit:checkpoint:create');
    }

    public function description(): string
    {
        return 'Create a deterministic security-audit checkpoint when the ledger changed.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        $result = $this->checkpoints->createWhenChanged();
        $output->writeln('Result: ' . ($result->created ? 'CREATED' : 'UNCHANGED'));
        $output->writeln('Streams: ' . $result->streamCount);
        $output->writeln('Events: ' . $result->eventCount);
        if ($result->created) {
            $output->writeln('Checkpoint: ' . $result->publicId);
            $output->writeln('Number: ' . $result->number);
        }

        return 0;
    }
}
