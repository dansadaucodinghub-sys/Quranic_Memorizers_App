<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap;

final readonly class RuntimeEnvironment
{
    /** @param list<string> $loadedExtensions */
    public function __construct(
        private string $phpVersion,
        private array $loadedExtensions,
    ) {
    }

    public function phpVersion(): string
    {
        return $this->phpVersion;
    }

    /** @return list<string> */
    public function loadedExtensions(): array
    {
        return $this->loadedExtensions;
    }
}
