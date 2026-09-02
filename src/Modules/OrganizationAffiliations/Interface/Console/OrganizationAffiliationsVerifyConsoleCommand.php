<?php

declare(strict_types=1);

namespace Qmdb\Modules\OrganizationAffiliations\Interface\Console;

use Qmdb\Modules\OrganizationAffiliations\Application\OrganizationAffiliationsReadinessCheck;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;

final readonly class OrganizationAffiliationsVerifyConsoleCommand implements ConsoleCommand
{
    public function __construct(private OrganizationAffiliationsReadinessCheck $readiness)
    {
    }
    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('organizations:affiliations:verify');
    }
    public function description(): string
    {
        return 'Verify private Organization affiliation integrity and lifecycle readiness.';
    }
    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        $report = $this->readiness->report();
        if (!$this->readiness->isReady()) {
            $output->write("Organization affiliations verification: FAIL\n");
            return 1;
        } $output->write(sprintf("Organization affiliations verification: PASS\nRole-definition count: %d\nPending affiliation count: %d\nActive affiliation count: %d\nSuspended affiliation count: %d\nEnded affiliation count: %d\nInvalid-row count: %d\n", $report['role_definitions'], $report['pending'], $report['active'], $report['suspended'], $report['ended'], $report['invalid_rows']));
        return 0;
    }
}
