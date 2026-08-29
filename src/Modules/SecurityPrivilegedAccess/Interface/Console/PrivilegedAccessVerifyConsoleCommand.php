<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityPrivilegedAccess\Interface\Console;

use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessSchemaVerifier;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;

final readonly class PrivilegedAccessVerifyConsoleCommand implements ConsoleCommand
{
    public function __construct(private PrivilegedAccessSchemaVerifier $verifier)
    {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('security:privileged-access:verify');
    }

    public function description(): string
    {
        return 'Verify privileged-access schema, seed, policy and active-access invariants.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        $report = $this->verifier->verify();
        $output->writeln('Privileged access verification: ' . ($report->isValid() ? 'PASS' : 'FAIL'));
        $output->writeln('Policies: ' . $report->policyCount);
        $output->writeln('Requests: ' . $report->requestCount);
        $output->writeln('Activations: ' . $report->activationCount);
        $output->writeln('Reviews: ' . $report->reviewCount);
        foreach ($report->errors as $error) {
            $output->errorLine($error);
        }

        return $report->isValid() ? 0 : 1;
    }
}
