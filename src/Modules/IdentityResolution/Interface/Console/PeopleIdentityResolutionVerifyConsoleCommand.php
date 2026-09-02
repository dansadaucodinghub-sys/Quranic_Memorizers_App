<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Interface\Console;

use Qmdb\Modules\IdentityResolution\Application\PeopleIdentityResolutionReadinessCheck;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;

final readonly class PeopleIdentityResolutionVerifyConsoleCommand implements ConsoleCommand
{
    public function __construct(private PeopleIdentityResolutionReadinessCheck $readiness)
    {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('people:identity-resolution:verify');
    }

    public function description(): string
    {
        return 'Verify private profile-claim, record-status, and duplicate-resolution integrity.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        $report = $this->readiness->report();
        if (!$this->readiness->isReady()) {
            $output->write("People identity resolution verification: FAIL\n");

            return 1;
        }
        $output->write(sprintf("People identity resolution verification: PASS\nActive pairing count: %d\nPending claim count: %d\nAccepted claim count: %d\nActive verification-assertion count: %d\nOpen duplicate-case count: %d\nBlocked duplicate-case count: %d\nResolved duplicate-case count: %d\nAlias count: %d\nInvalid-row count: %d\n", $report['active_pairings'], $report['pending_claims'], $report['accepted_claims'], $report['active_assertions'], $report['open_cases'], $report['blocked_cases'], $report['resolved_cases'], $report['aliases'], $report['invalid_rows']));

        return 0;
    }
}
