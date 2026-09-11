<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Interface\Console;

use PDO;
use PDOStatement;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;

final readonly class QuranSourcesVerifyConsoleCommand implements ConsoleCommand
{
    public function __construct(private DatabaseConnectionProvider $connections)
    {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('quran:sources:verify');
    }

    public function description(): string
    {
        return 'Verify approved Tanzil source definitions without reading source content.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        try {
            $statement = $this->connections->connection()->query(
                'SELECT source_code, source_version, content_role, attribution_required, verbatim_only, '
                . 'runtime_download_allowed, status FROM quran_reference_sources ORDER BY source_code',
            );
            if (!$statement instanceof PDOStatement) {
                throw new \RuntimeException('Source verification query preparation failed.');
            }
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
            $expected = [
                'TANZIL_QURAN_METADATA_1_0' => ['1.0', 'STRUCTURAL_METADATA'],
                'TANZIL_SIMPLE_CLEAN_1_1' => ['1.1', 'SEARCH_TEXT'],
                'TANZIL_UTHMANI_1_1' => ['1.1', 'CANONICAL_TEXT'],
            ];
            if (count($rows) !== count($expected)) {
                throw new \RuntimeException('Approved Qur’an source count differs.');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \RuntimeException('Approved Qur’an source row is invalid.');
                }
                $code = $this->requiredString($row, 'source_code');
                if (
                    !isset($expected[$code])
                    || [$this->requiredString($row, 'source_version'), $this->requiredString($row, 'content_role')] !== $expected[$code]
                    || $this->requiredInt($row, 'attribution_required') !== 1
                    || $this->requiredInt($row, 'verbatim_only') !== 1
                    || $this->requiredInt($row, 'runtime_download_allowed') !== 0
                    || $this->requiredString($row, 'status') !== 'APPROVED'
                ) {
                    throw new \RuntimeException('Approved Qur’an source definition is invalid.');
                }
            }
            $output->write("Qur’an source verification: PASS\nApproved source count: 3\n");

            return 0;
        } catch (\Throwable $exception) {
            $output->write("Qur’an source verification: FAIL\n" . $exception->getMessage() . "\n");

            return 1;
        }
    }

    /** @param array<mixed, mixed> $row */
    private function requiredString(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value)) {
            throw new \RuntimeException('Approved Qur’an source scalar is invalid.');
        }

        return $value;
    }

    /** @param array<mixed, mixed> $row */
    private function requiredInt(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new \RuntimeException('Approved Qur’an source scalar is invalid.');
    }
}
