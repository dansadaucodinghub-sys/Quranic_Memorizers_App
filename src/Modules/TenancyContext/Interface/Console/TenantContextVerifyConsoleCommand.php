<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Interface\Console;

use Qmdb\Modules\TenancyContext\Application\TenantContextSchemaVerifier;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;

final readonly class TenantContextVerifyConsoleCommand implements ConsoleCommand
{
    public function __construct(private TenantContextSchemaVerifier $verifier)
    {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('tenancy:context:verify');
    }

    public function description(): string
    {
        return 'Verify session-bound tenant-context schema and composite integrity.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        $report = $this->verifier->verify();
        $output->writeln('Tenant context verification: ' . ($report->isValid() ? 'PASS' : 'FAIL'));
        foreach ($report->errors as $error) {
            $output->errorLine($error);
        }

        return $report->isValid() ? 0 : 1;
    }
}
