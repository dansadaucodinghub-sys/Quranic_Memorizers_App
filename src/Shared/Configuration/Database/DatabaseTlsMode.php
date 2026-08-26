<?php

declare(strict_types=1);

namespace Qmdb\Shared\Configuration\Database;

enum DatabaseTlsMode: string
{
    case DISABLED = 'disabled';
    case VERIFY_SERVER = 'verify_server';

    public static function parse(string $value): self
    {
        return self::tryFrom(strtolower(trim($value)))
            ?? throw new DatabaseConfigurationException(
                'DB_CONFIG_INVALID_TLS_MODE',
                'Database TLS mode is invalid.',
            );
    }
}
