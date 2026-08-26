<?php

declare(strict_types=1);

namespace Qmdb\Tools\Build;

final readonly class ReleaseManifest
{
    /** @param array<string, mixed> $data */
    public function __construct(private array $data)
    {
        if (($data['project_code'] ?? null) !== 'QMDB' || !is_array($data['files'] ?? null)) {
            throw new \InvalidArgumentException('Release manifest structure is invalid.');
        }
    }

    /** @return array<string, mixed> */
    public function data(): array
    {
        return $this->data;
    }

    /** @return list<array{path: string, sha256: string, size: int, mode: string}> */
    public function files(): array
    {
        $files = $this->data['files'];
        if (!is_array($files) || !array_is_list($files)) {
            throw new \RuntimeException('Release manifest file list is invalid.');
        }
        /** @var list<array{path: string, sha256: string, size: int, mode: string}> $files */
        return $files;
    }
}
