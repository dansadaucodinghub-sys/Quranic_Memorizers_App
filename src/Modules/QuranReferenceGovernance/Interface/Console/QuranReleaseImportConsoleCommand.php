<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Interface\Console;

use Qmdb\Modules\QuranReferenceGovernance\Application\QuranBaselineReleaseInstaller;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;

final readonly class QuranReleaseImportConsoleCommand implements ConsoleCommand
{
    public function __construct(private QuranBaselineReleaseInstaller $installer)
    {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('quran:release:import');
    }

    public function description(): string
    {
        return 'Install the exact locked Tanzil B02 baseline release; supports --dry-run only as a safe preview.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions(['dry-run']);
        try {
            $result = $this->installer->install($input->requireFlag('dry-run'));
            $output->write(sprintf("Qur’an baseline import: PASS\nDry run: %s\nSurahs: %d\nAyahs: %d\nCanonical checksum: %s\n", $result['dry_run'] ? 'yes' : 'no', $result['surahs'], $result['ayahs'], $result['canonical_text_sha256']));

            return 0;
        } catch (\Throwable $exception) {
            $output->write("Qur’an baseline import: FAIL\n" . $exception->getMessage() . "\n");

            return 1;
        }
    }
}
