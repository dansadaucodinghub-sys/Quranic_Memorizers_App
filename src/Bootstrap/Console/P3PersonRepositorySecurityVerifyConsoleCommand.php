<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Console;

use Qmdb\Bootstrap\Security\P3PersonRepositorySecurityVerifier;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;

final readonly class P3PersonRepositorySecurityVerifyConsoleCommand implements ConsoleCommand
{
    public function __construct(private P3PersonRepositorySecurityVerifier $verifier)
    {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('security:person-repositories:verify');
    }

    public function description(): string
    {
        return 'Verify closed P3 Person SELF and Guardian repository authority scopes.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        $report = $this->verifier->verify();
        $output->writeln('Person repository verification: ' . ($report->isValid() ? 'PASS' : 'FAIL'));
        $output->writeln('Person repositories: ' . $report->repositoryCount);
        $output->writeln('Scope checks: ' . $report->scopeCheckCount);
        foreach ($report->errors as $error) {
            $output->errorLine($error);
        }

        return $report->isValid() ? 0 : 1;
    }
}
