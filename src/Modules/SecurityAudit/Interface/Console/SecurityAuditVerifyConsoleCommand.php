<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Interface\Console;

use Qmdb\Modules\SecurityAudit\Infrastructure\Persistence\SecurityAuditVerifier;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;

final readonly class SecurityAuditVerifyConsoleCommand implements ConsoleCommand
{
    public function __construct(private SecurityAuditVerifier $verifier)
    {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('security:audit:verify');
    }

    public function description(): string
    {
        return 'Verify keyed security-audit event chains without repairing data.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        $report = $this->verifier->verify();
        $output->writeln('Security audit verification: ' . ($report->isValid() ? 'PASS' : 'FAIL'));
        $output->writeln('Streams: ' . $report->streamCount);
        $output->writeln('Events: ' . $report->eventCount);
        $output->writeln('Checkpoints: ' . $report->checkpointCount);
        foreach ($report->errors as $error) {
            $output->errorLine($error);
        }

        return $report->isValid() ? 0 : 1;
    }
}
