<?php

declare(strict_types=1);

namespace Qmdb\Shared\Observability\Error;

use Throwable;

final readonly class ExceptionFingerprint
{
    public const LENGTH = 32;

    public function forThrowable(Throwable $throwable): string
    {
        return $this->forLocation(
            $throwable::class,
            basename($throwable->getFile()),
            $throwable->getLine(),
        );
    }

    public function forLocation(string $classification, string $file, int $line): string
    {
        return substr(hash('sha256', implode("\0", [
            $classification,
            basename($file),
            (string) $line,
        ])), 0, self::LENGTH);
    }
}
