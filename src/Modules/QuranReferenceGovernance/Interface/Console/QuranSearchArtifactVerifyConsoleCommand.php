<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Interface\Console;

use Qmdb\Modules\QuranReferenceGovernance\Application\TanzilSimpleCleanTextParser;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;

final readonly class QuranSearchArtifactVerifyConsoleCommand implements ConsoleCommand
{
    public function __construct(private string $root, private TanzilSimpleCleanTextParser $parser)
    {
    }
    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('quran:search-artifact:verify');
    }
    public function description(): string
    {
        return 'Verify the locked Tanzil Simple Clean search artifact without exposing its text.';
    }
    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        try {
            $path = $this->root . '/resources/data/quran/tanzil/search-artifacts.lock.json';
            $raw = file_get_contents($path);
            $lock = is_string($raw) ? json_decode($raw, true, 32, JSON_THROW_ON_ERROR) : null;
            if (!is_array($lock) || ($lock['schema'] ?? null) !== 'qmdb.quran.tanzil-artifacts-lock.v1') {
                throw new \RuntimeException('Search lock is invalid.');
            }
            $stored = $lock['lock_sha256'] ?? null;
            unset($lock['lock_sha256']);
            $canonical = $this->canonical($lock);
            if (!is_string($stored) || !hash_equals($stored, hash('sha256', $canonical))) {
                throw new \RuntimeException('Search lock checksum differs.');
            }
            $artifacts = $lock['artifacts'] ?? null;
            if (!is_array($artifacts)) {
                throw new \RuntimeException('Search lock artifact list is invalid.');
            }
            $artifact = null;
            foreach ($artifacts as $item) {
                if (is_array($item) && ($item['source_code'] ?? null) === 'TANZIL_SIMPLE_CLEAN_1_1') {
                    $artifact = $item;
                    break;
                }
            }
            if (!is_array($artifact) || !is_string($artifact['repository_relative_path'] ?? null) || !is_string($artifact['sha256'] ?? null)) {
                throw new \RuntimeException('Simple Clean artifact is absent.');
            }
            $file = $this->root . '/' . $artifact['repository_relative_path'];
            $checksum = is_file($file) ? hash_file('sha256', $file) : false;
            if (!is_string($checksum) || !hash_equals($artifact['sha256'], $checksum)) {
                throw new \RuntimeException('Simple Clean artifact checksum differs.');
            } $records = $this->parser->parse($file);
            $output->write("Qur’an search artifact verification: PASS\nSource: TANZIL_SIMPLE_CLEAN_1_1\nVersion: 1.1\nRows: " . count($records) . "\nSHA-256: {$artifact['sha256']}\n");
            return 0;
        } catch (\Throwable $e) {
            $output->write("Qur’an search artifact verification: FAIL\n{$e->getMessage()}\n");
            return 1;
        }
    }
    /** @param array<array-key, mixed> $value */
    private function canonical(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
