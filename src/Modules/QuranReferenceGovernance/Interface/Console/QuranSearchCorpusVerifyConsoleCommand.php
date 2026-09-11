<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Interface\Console;

use PDO;
use PDOStatement;
use Qmdb\Modules\QuranReferenceGovernance\Application\QuranSearchHasher;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;

final readonly class QuranSearchCorpusVerifyConsoleCommand implements ConsoleCommand
{
    public function __construct(private DatabaseConnectionProvider $connections)
    {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('quran:search-corpus:verify');
    }

    public function description(): string
    {
        return 'Verify active Simple Clean corpus cardinality and deterministic checksums.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        try {
            $pdo = $this->connections->connection();
            $corpusStatement = $pdo->query(
                "SELECT id, ayah_count, HEX(simple_text_sha256) AS corpus_hash\n"
                . "FROM quran_search_corpora WHERE status = 'ACTIVE' ORDER BY id DESC LIMIT 1",
            );
            if (!$corpusStatement instanceof PDOStatement) {
                throw new \RuntimeException('Search corpus verification query preparation failed.');
            }
            $corpus = $corpusStatement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($corpus)) {
                throw new \RuntimeException('No active search corpus.');
            }
            $corpusId = $this->requiredInt($corpus, 'id');
            $expectedCount = $this->requiredInt($corpus, 'ayah_count');
            $corpusHash = strtolower($this->requiredString($corpus, 'corpus_hash'));

            $rowsStatement = $pdo->prepare(
                'SELECT surah_number, ayah_number, simple_clean_text, text_byte_size, HEX(text_sha256) AS sha256 '
                . 'FROM quran_ayah_search_texts WHERE corpus_id = :corpus ORDER BY global_ayah_ordinal',
            );
            if (!$rowsStatement instanceof PDOStatement) {
                throw new \RuntimeException('Search corpus verification query preparation failed.');
            }
            $rowsStatement->execute([':corpus' => $corpusId]);
            $rows = $rowsStatement->fetchAll(PDO::FETCH_ASSOC);
            if (count($rows) !== $expectedCount) {
                throw new \RuntimeException('Search corpus cardinality differs.');
            }

            $records = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \RuntimeException('Search corpus row is invalid.');
                }
                $surahNumber = $this->requiredInt($row, 'surah_number');
                $ayahNumber = $this->requiredInt($row, 'ayah_number');
                $text = $this->requiredString($row, 'simple_clean_text');
                $checksum = strtolower($this->requiredString($row, 'sha256'));
                if (
                    strlen($text) !== $this->requiredInt($row, 'text_byte_size')
                    || !hash_equals($checksum, QuranSearchHasher::row($surahNumber, $ayahNumber, $text))
                ) {
                    throw new \RuntimeException('Search row checksum differs.');
                }
                $records[] = ['surah_number' => $surahNumber, 'ayah_number' => $ayahNumber, 'text' => $text, 'byte_size' => strlen($text), 'sha256' => $checksum];
            }
            if (!hash_equals($corpusHash, QuranSearchHasher::corpus($records))) {
                throw new \RuntimeException('Search corpus checksum differs.');
            }
            $output->write("Qur’an search corpus verification: PASS\nRows: " . count($records) . "\n");

            return 0;
        } catch (\Throwable $exception) {
            $output->write("Qur’an search corpus verification: FAIL\n" . $exception->getMessage() . "\n");

            return 1;
        }
    }

    /** @param array<mixed, mixed> $row */
    private function requiredString(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value)) {
            throw new \RuntimeException('Search corpus scalar is invalid.');
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

        throw new \RuntimeException('Search corpus scalar is invalid.');
    }
}
