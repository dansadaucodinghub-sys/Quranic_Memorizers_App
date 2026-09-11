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

final readonly class QuranContentVerifyConsoleCommand implements ConsoleCommand
{
    public function __construct(private DatabaseConnectionProvider $connections)
    {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('quran:content:verify');
    }
    public function description(): string
    {
        return 'Verify active canonical content and its optional active search overlay.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        try {
            $pdo = $this->connections->connection();
            $release = $this->statement($pdo->query("SELECT r.id,s.ayah_count FROM quran_reference_releases r INNER JOIN quran_release_content_summaries s ON s.release_id=r.id WHERE r.status='ACTIVE' ORDER BY r.id DESC LIMIT 1"))->fetch(PDO::FETCH_ASSOC);
            if (!is_array($release) || !is_int($release['id'] ?? null) || !is_int($release['ayah_count'] ?? null)) {
                throw new \RuntimeException('No active canonical release.');
            }
            $count = $this->statement($pdo->prepare('SELECT COUNT(*) FROM quran_ayahs WHERE release_id=:id'));
            $count->execute([':id' => $release['id']]);
            if ($this->count($count) !== $release['ayah_count']) {
                throw new \RuntimeException('Canonical Ayah count differs from its summary.');
            }
            $corpus = $this->statement($pdo->prepare("SELECT id,ayah_count FROM quran_search_corpora WHERE canonical_release_id=:id AND status='ACTIVE'"));
            $corpus->execute([':id' => $release['id']]);
            $corpusRow = $corpus->fetch(PDO::FETCH_ASSOC);
            if (is_array($corpusRow) && is_int($corpusRow['id'] ?? null) && is_int($corpusRow['ayah_count'] ?? null)) {
                $rows = $this->statement($pdo->prepare('SELECT COUNT(*) FROM quran_ayah_search_texts WHERE corpus_id=:id'));
                $rows->execute([':id' => $corpusRow['id']]);
                if ($this->count($rows) !== $corpusRow['ayah_count']) {
                    throw new \RuntimeException('Search corpus count differs.');
                }
            }
            $output->write("Qur’an content verification: PASS\nCanonical Ayahs: {$release['ayah_count']}\nSearch corpus: " . (is_array($corpusRow) ? 'ACTIVE' : 'NOT INSTALLED') . "\n");
            return 0;
        } catch (\Throwable $error) {
            $output->write("Qur’an content verification: FAIL\n{$error->getMessage()}\n");
            return 1;
        }
    }

    private function statement(PDOStatement|false $statement): PDOStatement
    {
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Content verification query preparation failed.');
        }
        return $statement;
    }

    private function count(PDOStatement $statement): int
    {
        $value = $statement->fetchColumn();
        if (!is_int($value) && !is_string($value)) {
            throw new \RuntimeException('Content verification count is invalid.');
        }
        return (int) $value;
    }
}
