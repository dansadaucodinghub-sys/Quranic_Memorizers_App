<?php

declare(strict_types=1);

namespace Qmdb\Tools\Support;

final readonly class GitMetadata
{
    public function __construct(
        public string $revision,
        public string $shortRevision,
        public int $sourceDateEpoch,
        public string $sourceState,
    ) {
    }

    public static function inspect(string $root, ?ProcessRunner $runner = null): self
    {
        $runner ??= new ProcessRunner();
        $revision = self::required($runner->run(['git', 'rev-parse', 'HEAD'], $root), 'Git revision');
        $short = self::required($runner->run(['git', 'rev-parse', '--short=12', 'HEAD'], $root), 'Git revision');
        $epoch = self::required($runner->run(['git', 'show', '-s', '--format=%ct', 'HEAD'], $root), 'Git timestamp');
        $status = $runner->run(['git', 'status', '--porcelain=v1', '--untracked-files=all'], $root);
        if ($status->exitCode !== 0) {
            throw new \RuntimeException('Unable to determine Git source state.');
        }
        if (!ctype_digit($epoch)) {
            throw new \RuntimeException('Git source timestamp is invalid.');
        }
        return new self($revision, $short, (int) $epoch, trim($status->stdout) === '' ? 'clean' : 'dirty');
    }

    private static function required(ProcessResult $result, string $label): string
    {
        $value = trim($result->stdout);
        if ($result->exitCode !== 0 || $value === '') {
            throw new \RuntimeException($label . ' is unavailable.');
        }
        return $value;
    }
}
