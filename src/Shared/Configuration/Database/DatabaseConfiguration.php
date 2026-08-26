<?php

declare(strict_types=1);

namespace Qmdb\Shared\Configuration\Database;

use Qmdb\Shared\Configuration\ApplicationEnvironment;
use Qmdb\Shared\Database\Transaction\TransactionRetryPolicy;

final readonly class DatabaseConfiguration
{
    public function __construct(
        private string $host,
        private int $port,
        private string $databaseName,
        private string $username,
        private DatabaseTlsMode $tlsMode,
        private ?string $tlsCaFile,
        private int $connectTimeoutSeconds,
        private TransactionRetryPolicy $deadlockRetryPolicy,
        private ApplicationEnvironment $environment,
    ) {
        self::assertHost($host);
        if ($port < 1 || $port > 65_535) {
            throw new DatabaseConfigurationException('DB_CONFIG_INVALID_PORT', 'Database port is invalid.');
        }
        if (preg_match('/\A[A-Za-z0-9_]+\z/', $databaseName) !== 1) {
            throw new DatabaseConfigurationException(
                'DB_CONFIG_INVALID_DATABASE_NAME',
                'Database name is invalid.',
            );
        }
        if (
            trim($username) === ''
            || preg_match('/[\x00-\x1F\x7F]/', $username) === 1
        ) {
            throw new DatabaseConfigurationException('DB_CONFIG_INVALID_USERNAME', 'Database username is invalid.');
        }
        if (strtolower(trim($username)) === 'root' || str_starts_with(strtolower(trim($username)), 'root@')) {
            throw new DatabaseConfigurationException(
                'DB_CONFIG_ROOT_PROHIBITED',
                'Root database identity is prohibited.',
            );
        }
        if ($connectTimeoutSeconds < 1 || $connectTimeoutSeconds > 60) {
            throw new DatabaseConfigurationException(
                'DB_CONFIG_INVALID_TIMEOUT',
                'Database timeout is invalid.',
            );
        }
        if ($environment->isProductionLike() && $tlsMode !== DatabaseTlsMode::VERIFY_SERVER) {
            throw new DatabaseConfigurationException(
                'DB_CONFIG_TLS_REQUIRED',
                'Verified database TLS is required in production-like environments.',
            );
        }
        if ($tlsMode === DatabaseTlsMode::VERIFY_SERVER) {
            if ($tlsCaFile === null || trim($tlsCaFile) === '') {
                throw new DatabaseConfigurationException(
                    'DB_CONFIG_TLS_CA_REQUIRED',
                    'Database TLS CA file is required.',
                );
            }
            if (!is_file($tlsCaFile) || !is_readable($tlsCaFile)) {
                throw new DatabaseConfigurationException(
                    'DB_CONFIG_TLS_CA_UNREADABLE',
                    'Database TLS CA file is not readable.',
                );
            }
        }
    }

    public function host(): string
    {
        return $this->host;
    }
    public function port(): int
    {
        return $this->port;
    }
    public function databaseName(): string
    {
        return $this->databaseName;
    }
    public function username(): string
    {
        return $this->username;
    }
    public function tlsMode(): DatabaseTlsMode
    {
        return $this->tlsMode;
    }
    public function tlsCaFile(): ?string
    {
        return $this->tlsCaFile;
    }
    public function connectTimeoutSeconds(): int
    {
        return $this->connectTimeoutSeconds;
    }
    public function deadlockRetryPolicy(): TransactionRetryPolicy
    {
        return $this->deadlockRetryPolicy;
    }
    public function environment(): ApplicationEnvironment
    {
        return $this->environment;
    }

    /** @return array{host: string, port: int, database: string, username: string, tls_mode: string, connect_timeout_seconds: int} */
    public function toSafeArray(): array
    {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->databaseName,
            'username' => $this->username,
            'tls_mode' => $this->tlsMode->value,
            'connect_timeout_seconds' => $this->connectTimeoutSeconds,
        ];
    }

    private static function assertHost(string $host): void
    {
        if (trim($host) === '' || str_contains($host, ';') || preg_match('/[\x00-\x1F\x7F]/', $host) === 1) {
            throw new DatabaseConfigurationException('DB_CONFIG_INVALID_HOST', 'Database host is invalid.');
        }
    }
}
