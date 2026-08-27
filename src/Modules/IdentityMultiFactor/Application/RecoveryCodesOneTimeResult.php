<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Application;

final readonly class RecoveryCodesOneTimeResult
{
    /** @param list<string> $codes */
    public function __construct(public array $codes)
    {
        if ($codes === []) {
            throw new \InvalidArgumentException('One-time recovery codes must not be empty.');
        }
    }

    /** @return array{codes: string} */
    public function __debugInfo(): array
    {
        return ['codes' => '[REDACTED]'];
    }
}
