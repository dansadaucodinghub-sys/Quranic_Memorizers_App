<?php

declare(strict_types=1);

namespace Qmdb\Shared\Infrastructure\Persistence\MySql\Connection;

use PDO;
use Qmdb\Shared\Configuration\Database\DatabaseConfiguration;
use Qmdb\Shared\Configuration\Database\DatabaseTlsMode;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Exception\DatabaseVerificationException;
use Throwable;

final readonly class MySqlServerVerifier
{
    public function __construct(private DatabaseConfiguration $configuration)
    {
    }

    public function verify(PDO $connection): MySqlVerificationReport
    {
        try {
            $statement = $connection->query(
                'SELECT VERSION() AS server_version, @@default_storage_engine AS default_engine, '
                . '@@version_comment AS server_comment, CURRENT_USER() AS runtime_identity',
            );
            $row = $statement === false ? false : $statement->fetch(PDO::FETCH_ASSOC);
            $tlsStatement = $connection->query("SHOW STATUS LIKE 'Ssl_cipher'");
            $tlsRow = $tlsStatement === false ? false : $tlsStatement->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $exception) {
            throw new DatabaseVerificationException('DB_VERIFY_SERVER_QUERY_FAILED', $exception);
        }
        if (!is_array($row)) {
            throw new DatabaseVerificationException('DB_VERIFY_SERVER_RESULT_INVALID');
        }
        $version = strtolower($this->stringField($row, 'server_version'));
        $comment = strtolower($this->stringField($row, 'server_comment'));
        if ($version === '' || str_contains($version, 'mariadb') || !str_contains($comment, 'mysql')) {
            throw new DatabaseVerificationException('DB_VERIFY_SERVER_UNAPPROVED');
        }
        if (strtolower($this->stringField($row, 'default_engine')) !== 'innodb') {
            throw new DatabaseVerificationException('DB_VERIFY_ENGINE_INVALID');
        }
        $identity = strtolower($this->stringField($row, 'runtime_identity'));
        if ($identity === 'root' || str_starts_with($identity, 'root@')) {
            throw new DatabaseVerificationException('DB_VERIFY_ROOT_PROHIBITED');
        }
        if ($this->configuration->tlsMode() === DatabaseTlsMode::VERIFY_SERVER) {
            $cipher = is_array($tlsRow)
                ? $this->stringField($tlsRow, isset($tlsRow['Value']) ? 'Value' : 'value')
                : '';
            if ($cipher === '') {
                throw new DatabaseVerificationException('DB_VERIFY_TLS_REQUIRED');
            }
        }

        return new MySqlVerificationReport(['mysql_server', 'innodb', 'non_root_identity', 'transport']);
    }

    /** @param array<array-key, mixed> $row */
    private function stringField(array $row, string $field): string
    {
        $value = $row[$field] ?? null;

        return is_string($value) ? $value : '';
    }
}
