<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Interface\Console;

use Qmdb\Modules\People\Application\PeopleProfilesReadinessCheck;
use Qmdb\Modules\People\Configuration\PeopleProfilesConfiguration;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;

final readonly class PeopleProfilesVerifyConsoleCommand implements ConsoleCommand
{
    public function __construct(private PeopleProfilesConfiguration $configuration, private PeopleProfilesReadinessCheck $readiness)
    {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('people:profiles:verify');
    }

    public function description(): string
    {
        return 'Verify the private Person, role, and guardianship profile foundation configuration.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        $report = $this->readiness->verificationReport();
        if (!$this->readiness->isReady()) {
            $output->write("People profiles verification: FAIL\n");

            return 1;
        }
        $output->write(sprintf(
            "People profiles verification: PASS\nMinor threshold: %d\nMaximum age: %d\nGuardian dependent limit: %d\nPerson count: %d\nSelf-linked Person count: %d\nDependent Person count: %d\nActive role-profile count: %d\nActive guardianship count: %d\nInvalid-row count: %d\n",
            $this->configuration->minorThresholdYears,
            $this->configuration->maximumAgeYears,
            $this->configuration->maximumDependentsPerGuardian,
            $report['person_count'],
            $report['self_linked_person_count'],
            $report['dependent_person_count'],
            $report['active_role_profile_count'],
            $report['active_guardianship_count'],
            $report['invalid_rows'],
        ));

        return 0;
    }
}
