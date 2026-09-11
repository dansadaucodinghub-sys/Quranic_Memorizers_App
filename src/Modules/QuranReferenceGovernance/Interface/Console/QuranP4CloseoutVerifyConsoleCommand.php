<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Interface\Console;

use Qmdb\Modules\QuranReferenceGovernance\Application\QuranP4CloseoutReadinessCheck;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;

final readonly class QuranP4CloseoutVerifyConsoleCommand implements ConsoleCommand
{
    public function __construct(private QuranP4CloseoutReadinessCheck $readiness)
    {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('quran:p4:closeout:verify');
    }

    public function description(): string
    {
        return 'Verify the complete P4 runtime without changing data or formal closeout state.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        try {
            $report = $this->readiness->verify();
            $output->write(sprintf(
                "Qur’an P4 closeout readiness: PASS\nRequirements: %d\nSources: %d\nCanonical Ayahs: %d\nSearch Ayahs: %d\nPublic routes: %d\nPrivate governance routes: %d\nFormal closeout: NOT PERFORMED\n",
                $report['requirements'],
                $report['sources'],
                $report['canonical_ayahs'],
                $report['search_ayahs'],
                $report['public_routes'],
                $report['private_routes'],
            ));

            return 0;
        } catch (\Throwable $exception) {
            $output->write("Qur’an P4 closeout readiness: FAIL\n" . $exception->getMessage() . "\n");

            return 1;
        }
    }
}
