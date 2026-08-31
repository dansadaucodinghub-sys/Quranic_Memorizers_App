<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Interface\Console;

use Qmdb\Modules\TenancyContext\Application\TenantRepositorySecurityVerifier;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;

final readonly class TenantRepositorySecurityVerifyConsoleCommand implements ConsoleCommand
{
    public function __construct(private TenantRepositorySecurityVerifier $verifier)
    {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('security:tenant-repositories:verify');
    }

    public function description(): string
    {
        return 'Verify P2 tenant-owned repository context and SQL-scope boundaries.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        $report = $this->verifier->verify();
        $output->writeln('Tenant repository verification: ' . ($report->isValid() ? 'PASS' : 'FAIL'));
        $output->writeln('Tenant repositories: ' . $report->tenantRepositoryCount);
        $output->writeln('Tenant repository methods: ' . $report->tenantRepositoryMethodCount);
        $output->writeln('Explicit global repositories: ' . $report->explicitGlobalRepositoryCount);
        foreach ($report->errors as $error) {
            $output->errorLine($error);
        }

        return $report->isValid() ? 0 : 1;
    }
}
