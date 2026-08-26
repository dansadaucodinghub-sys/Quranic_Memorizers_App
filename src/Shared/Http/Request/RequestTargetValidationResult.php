<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Request;

use LogicException;

final readonly class RequestTargetValidationResult
{
    private function __construct(
        private bool $valid,
        private ?string $decodedPath,
    ) {
    }

    public static function valid(string $decodedPath): self
    {
        return new self(true, $decodedPath);
    }

    public static function invalid(): self
    {
        return new self(false, null);
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function decodedPath(): string
    {
        if (!$this->valid || $this->decodedPath === null) {
            throw new LogicException('An invalid request target has no decoded path.');
        }

        return $this->decodedPath;
    }
}
