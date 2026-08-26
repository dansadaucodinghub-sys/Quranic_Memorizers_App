<?php

declare(strict_types=1);

namespace Qmdb\Shared\Presentation\View;

use RuntimeException;

final readonly class ViewRegistry
{
    /** @var array<string, string> */
    private array $files;

    /** @param array<string, string> $files */
    public function __construct(array $files)
    {
        $validated = [];
        foreach ($files as $name => $file) {
            $canonical = (new ViewName($name))->value();
            if (isset($validated[$canonical])) {
                throw new RuntimeException('Duplicate view registration.');
            }
            if (!is_file($file)) {
                throw new RuntimeException('A registered view file is missing.');
            }
            $validated[$canonical] = $file;
        }
        $this->files = $validated;
    }

    public function file(ViewName $name): string
    {
        return $this->files[$name->value()] ?? throw new RuntimeException('Unknown view.');
    }

    public function count(): int
    {
        return count($this->files);
    }
}
