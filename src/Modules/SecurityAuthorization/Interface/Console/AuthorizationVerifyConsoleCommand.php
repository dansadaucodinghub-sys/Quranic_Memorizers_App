<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Interface\Console;

use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationCatalogVerifier;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;

final readonly class AuthorizationVerifyConsoleCommand implements ConsoleCommand
{
    public function __construct(private AuthorizationCatalogVerifier $verifier)
    {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('security:authorization:verify');
    }

    public function description(): string
    {
        return 'Verify authorization migrations, seed, catalog and protected-role invariants.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        $report = $this->verifier->verify();
        $output->writeln('Authorization verification: ' . ($report->isValid() ? 'PASS' : 'FAIL'));
        $output->writeln('Permissions: ' . $report->permissionCount);
        $output->writeln('Roles: ' . $report->roleCount);
        $output->writeln('Role-permission mappings: ' . $report->mappingCount);
        $output->writeln('Platform assignments: ' . $report->platformAssignmentCount);
        $output->writeln('Workspace assignments: ' . $report->workspaceAssignmentCount);
        if (!$report->isValid()) {
            foreach ($report->errors as $error) {
                $output->errorLine($error);
            }
        }

        return $report->isValid() ? 0 : 1;
    }
}
