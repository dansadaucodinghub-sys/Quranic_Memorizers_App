<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Console;

use Qmdb\Bootstrap\Security\P2SecurityHardeningVerifier;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;

final readonly class P2SecurityHardeningVerifyConsoleCommand implements ConsoleCommand
{
    public function __construct(private P2SecurityHardeningVerifier $verifier)
    {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('security:p2:verify');
    }

    public function description(): string
    {
        return 'Run bounded, read-only P2 security boundary verification.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        $report = $this->verifier->verify();
        $output->writeln('P2 security verification: ' . ($report->isValid() ? 'PASS' : 'FAIL'));
        $output->writeln('Components checked: ' . count($report->components));
        foreach ($report->components as $name => $valid) {
            $output->writeln($name . ': ' . ($valid ? 'PASS' : 'FAIL'));
        }
        foreach ($report->errors as $error) {
            $output->errorLine($error);
        }

        return $report->isValid() ? 0 : 1;
    }
}
