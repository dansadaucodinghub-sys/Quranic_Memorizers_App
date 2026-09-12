<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Interface\Console;

use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;

final readonly class QuranB02ArtifactsVerifyConsoleCommand implements ConsoleCommand
{
    public function __construct(private string $projectRoot)
    {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('quran:artifacts:verify');
    }

    public function description(): string
    {
        return 'Verify the two locked Tanzil B02 source artifacts without importing them.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        try {
            $path = $this->projectRoot . '/resources/data/quran/tanzil/b02-source-artifacts.lock.json';
            $contents = file_get_contents($path);
            $lock = is_string($contents) ? json_decode($contents, true, 32, JSON_THROW_ON_ERROR) : null;
            if (!is_array($lock) || ($lock['schema'] ?? null) !== 'qmdb.quran.b02-source-artifacts-lock.v1') {
                throw new \InvalidArgumentException('B02 source-artifact lock is invalid.');
            }
            $actualHash = $lock['lock_sha256'] ?? null;
            unset($lock['lock_sha256']);
            if (!is_string($actualHash) || !hash_equals($actualHash, hash('sha256', $this->canonical($lock)))) {
                throw new \InvalidArgumentException('B02 source-artifact lock checksum differs.');
            }
            $artifacts = $lock['artifacts'] ?? null;
            if (!is_array($artifacts) || count($artifacts) !== 2) {
                throw new \InvalidArgumentException('B02 lock must contain exactly two artifacts.');
            }
            $codes = [];
            foreach ($artifacts as $artifact) {
                if (!is_array($artifact) || !is_string($artifact['repository_relative_path'] ?? null) || !is_string($artifact['sha256'] ?? null)) {
                    throw new \InvalidArgumentException('B02 artifact entry is invalid.');
                }
                $relative = $artifact['repository_relative_path'];
                if (str_contains($relative, '..') || str_starts_with($relative, '/') || str_contains($relative, ':')) {
                    throw new \InvalidArgumentException('B02 artifact path is unsafe.');
                }
                $file = $this->projectRoot . '/' . $relative;
                if (!is_file($file) || hash_file('sha256', $file) !== $artifact['sha256']) {
                    throw new \InvalidArgumentException('B02 source artifact checksum differs.');
                }
                $sourceCode = $artifact['source_code'] ?? null;
                if (!is_string($sourceCode)) {
                    throw new \InvalidArgumentException('B02 artifact source code is invalid.');
                }
                $codes[] = $sourceCode;
            }
            if ($codes !== ['TANZIL_UTHMANI_1_1', 'TANZIL_QURAN_METADATA_1_0']) {
                throw new \InvalidArgumentException('B02 lock source scope is invalid.');
            }
            $noticePath = $lock['notice_path'] ?? null;
            $noticeHash = $lock['notice_sha256'] ?? null;
            if (!is_string($noticePath) || !is_string($noticeHash)) {
                throw new \InvalidArgumentException('Tanzil notice lock is invalid.');
            }
            $notice = $this->projectRoot . '/' . $noticePath;
            if (!is_file($notice) || hash_file('sha256', $notice) !== $noticeHash) {
                throw new \InvalidArgumentException('Tanzil notice checksum differs.');
            }
            $output->write("Qur’an B02 artifacts verification: PASS\nArtifact count: 2\n");

            return 0;
        } catch (\Throwable $exception) {
            $output->write("Qur’an B02 artifacts verification: FAIL\n" . $exception->getMessage() . "\n");

            return 1;
        }
    }

    /** @param array<array-key, mixed> $value */
    private function canonical(array $value): string
    {
        $this->sortKeys($value);

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** @param array<array-key, mixed> $value */
    private function sortKeys(array &$value): void
    {
        foreach ($value as &$entry) {
            if (is_array($entry)) {
                $this->sortKeys($entry);
            }
        }
        unset($entry);
        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }
    }
}
