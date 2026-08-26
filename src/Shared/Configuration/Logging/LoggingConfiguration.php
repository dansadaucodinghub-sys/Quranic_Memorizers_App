<?php

declare(strict_types=1);

namespace Qmdb\Shared\Configuration\Logging;

final readonly class LoggingConfiguration
{
    public const CHANNEL = 'qmdb';
    public const OUTPUT = 'stderr';
    public const FORMAT = 'json';
    public const TIMEZONE = 'UTC';

    public function __construct(private LogLevel $minimumLevel)
    {
    }

    public function minimumLevel(): LogLevel
    {
        return $this->minimumLevel;
    }

    /** @return array{level: string, output: string, format: string, timezone: string} */
    public function toSafeArray(): array
    {
        return [
            'level' => $this->minimumLevel->value,
            'output' => self::OUTPUT,
            'format' => self::FORMAT,
            'timezone' => self::TIMEZONE,
        ];
    }
}
