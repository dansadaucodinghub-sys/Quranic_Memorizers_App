<?php

declare(strict_types=1);

namespace Qmdb\Shared\Configuration\Logging;

use InvalidArgumentException;
use Monolog\Level;

enum LogLevel: string
{
    case DEBUG = 'debug';
    case INFO = 'info';
    case NOTICE = 'notice';
    case WARNING = 'warning';
    case ERROR = 'error';
    case CRITICAL = 'critical';
    case ALERT = 'alert';
    case EMERGENCY = 'emergency';

    public static function parse(string $value): self
    {
        return self::tryFrom(strtolower(trim($value)))
            ?? throw new InvalidArgumentException('Unsupported application log level.');
    }

    public function toMonologLevel(): Level
    {
        return match ($this) {
            self::DEBUG => Level::Debug,
            self::INFO => Level::Info,
            self::NOTICE => Level::Notice,
            self::WARNING => Level::Warning,
            self::ERROR => Level::Error,
            self::CRITICAL => Level::Critical,
            self::ALERT => Level::Alert,
            self::EMERGENCY => Level::Emergency,
        };
    }
}
