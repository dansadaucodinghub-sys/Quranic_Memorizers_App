<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap;

final readonly class RuntimeViolation
{
    public function __construct(
        private string $code,
        private string $message,
    ) {
    }

    public function code(): string
    {
        return $this->code;
    }

    public function message(): string
    {
        return $this->message;
    }

    /** @return array{code: string, message: string} */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
        ];
    }
}
