<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Interface\Console;

use Qmdb\Modules\QuranReferenceGovernance\Application\QuranBaselineSearchCorpusInstaller;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;

final readonly class QuranSearchCorpusImportConsoleCommand implements ConsoleCommand
{
    public function __construct(private QuranBaselineSearchCorpusInstaller $installer)
    {
    }
    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('quran:search-corpus:import');
    }
    public function description(): string
    {
        return 'Import the locked Simple Clean search overlay for the active canonical release.';
    }
    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions(['dry-run']);
        try {
            $result = $this->installer->install($input->requireFlag('dry-run'));
            $output->write("Qur’an search corpus import: PASS\nDry run: " . ($result['dry_run'] ? 'yes' : 'no') . "\nAyahs: {$result['ayahs']}\nCorpus checksum: {$result['corpus_sha256']}\nAlignment checksum: {$result['alignment_sha256']}\n");
            return 0;
        } catch (\Throwable $e) {
            $output->write("Qur’an search corpus import: FAIL\n{$e->getMessage()}\n");
            return 1;
        }
    }
}
