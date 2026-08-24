<?php

declare(strict_types=1);

namespace Qmdb\Shared\Configuration;

enum ConfigurationSource: string
{
    case PROCESS = 'process';
    case LOCAL_ENV_FILE = 'local_env_file';
    case PROCESS_AND_LOCAL_ENV_FILE = 'process_and_local_env_file';

    public function includesLocalEnvironmentFile(): bool
    {
        return $this !== self::PROCESS;
    }

    public function toSafeDisplay(): string
    {
        return match ($this) {
            self::PROCESS => 'process environment',
            self::LOCAL_ENV_FILE => 'local environment file',
            self::PROCESS_AND_LOCAL_ENV_FILE => 'process environment with local file defaults',
        };
    }
}
