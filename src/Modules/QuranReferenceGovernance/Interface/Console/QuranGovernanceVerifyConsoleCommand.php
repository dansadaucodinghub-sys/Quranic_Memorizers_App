<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Interface\Console;

use PDO;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;

final readonly class QuranGovernanceVerifyConsoleCommand implements ConsoleCommand
{
    public function __construct(private DatabaseConnectionProvider $connections)
    {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('quran:governance:verify');
    }

    public function description(): string
    {
        return 'Verify global Qur’an source and release governance without importing text.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        try {
            $connection = $this->connections->connection();
            $valid = true;
            $table = $connection->prepare(
                'SELECT COUNT(*) FROM information_schema.tables '
                . 'WHERE table_schema = DATABASE() AND table_name = :table',
            );
            foreach (
                [
                'quran_reference_sources',
                'quran_source_artifacts',
                'quran_reference_releases',
                'quran_release_artifacts',
                'quran_release_manifests',
                'quran_release_validations',
                'quran_release_events',
                ] as $tableName
            ) {
                $table->execute([':table' => $tableName]);
                $valid = $valid && (int) $table->fetchColumn() === 1;
            }
            $sources = $connection->prepare(
                'SELECT source_code, source_reference, runtime_download_allowed, status '
                . 'FROM quran_reference_sources WHERE source_code = :source_code',
            );
            $expectedSources = [
                'TANZIL_UTHMANI_1_1' => 'https://tanzil.net/download/',
                'TANZIL_QURAN_METADATA_1_0' => 'https://tanzil.net/docs/quran_metadata',
                'TANZIL_SIMPLE_CLEAN_1_1' => 'https://tanzil.net/download/',
            ];
            foreach ($expectedSources as $sourceCode => $reference) {
                $sources->execute([':source_code' => $sourceCode]);
                $source = $sources->fetch(PDO::FETCH_ASSOC);
                $valid = $valid
                    && is_array($source)
                    && ($source['source_reference'] ?? null) === $reference
                    && in_array($source['runtime_download_allowed'] ?? null, [0, '0'], true)
                    && ($source['status'] ?? null) === 'APPROVED';
            }
        } catch (\Throwable) {
            $valid = false;
        }
        $output->write(
            'Qur’an governance verification: ' . ($valid ? "PASS\n" : "FAIL\n")
            . "Canonical text tables: 0\n"
            . 'Approved source count: ' . ($valid ? "3\n" : "0\n"),
        );

        return $valid ? 0 : 1;
    }
}
