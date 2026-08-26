<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Infrastructure\Persistence;

use DateTimeImmutable;
use PDO;
use Qmdb\Modules\Identity\Domain\AccountContactStatus;
use Qmdb\Modules\Identity\Domain\AccountStatus;
use Qmdb\Modules\Identity\Domain\CredentialStatus;
use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\Identity\Domain\Value\LookupHash;
use Qmdb\Modules\Identity\Domain\Value\SensitivePasswordHash;
use Qmdb\Modules\IdentityAccess\Application\Authentication\PasswordAuthenticationRecord;
use Qmdb\Modules\IdentityAccess\Application\Authentication\PasswordAuthenticationRepository;
use Qmdb\Modules\IdentityAccess\Security\Password\PasswordHashResult;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use UnexpectedValueException;

final readonly class MySqlPasswordAuthenticationRepository implements PasswordAuthenticationRepository
{
    public function __construct(private DatabaseConnectionProvider $provider)
    {
    }

    public function byEmailHash(LookupHash $lookupHash): ?PasswordAuthenticationRecord
    {
        $statement = $this->provider->connection()->prepare(
            'SELECT a.id, a.public_id, a.account_status, e.status_code AS email_status, '
            . 'c.credential_status, c.password_hash, c.algorithm, c.metadata_version '
            . 'FROM account_email_addresses e INNER JOIN user_accounts a ON a.id = e.user_account_id '
            . 'INNER JOIN account_credentials c ON c.user_account_id = a.id '
            . "AND c.credential_type = 'PASSWORD' AND c.credential_status = 'ACTIVE' "
            . 'WHERE e.active_lookup_hash = :lookup_hash LIMIT 1',
        );
        $statement->bindValue(':lookup_hash', $lookupHash->toBinary(), PDO::PARAM_LOB);
        $statement->execute();
        $value = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($value)) {
            return null;
        }
        $row = [];
        foreach ($value as $column => $field) {
            if (!is_string($column)) {
                throw new UnexpectedValueException('Authentication persistence row is invalid.');
            }
            $row[$column] = $field;
        }

        return new PasswordAuthenticationRecord(
            self::requiredInteger($row, 'id'),
            AccountId::fromBinary(self::requiredString($row, 'public_id')),
            AccountStatus::from(self::requiredString($row, 'account_status')),
            AccountContactStatus::from(self::requiredString($row, 'email_status')),
            CredentialStatus::from(self::requiredString($row, 'credential_status')),
            new SensitivePasswordHash(self::requiredString($row, 'password_hash')),
            self::requiredString($row, 'algorithm'),
            self::requiredInteger($row, 'metadata_version'),
        );
    }

    public function replacePasswordHash(
        int $accountInternalId,
        PasswordHashResult $hash,
        DateTimeImmutable $updatedAt,
    ): bool {
        $statement = $this->provider->connection()->prepare(
            'UPDATE account_credentials SET password_hash = :password_hash, algorithm = :algorithm, '
            . 'metadata_version = :metadata_version, updated_at = :updated_at, version = version + 1 '
            . "WHERE user_account_id = :account_id AND credential_type = 'PASSWORD' "
            . "AND credential_status = 'ACTIVE'",
        );
        $statement->execute([
            ':password_hash' => $hash->hash->revealForPersistence(),
            ':algorithm' => $hash->algorithm,
            ':metadata_version' => $hash->metadataVersion,
            ':updated_at' => $updatedAt->format('Y-m-d H:i:s.u'),
            ':account_id' => $accountInternalId,
        ]);

        return $statement->rowCount() === 1;
    }

    /** @param array<string, mixed> $row */
    private static function requiredString(array $row, string $column): string
    {
        $value = $row[$column] ?? null;
        if (!is_string($value)) {
            throw new UnexpectedValueException('Authentication persistence row is invalid.');
        }

        return $value;
    }

    /** @param array<string, mixed> $row */
    private static function requiredInteger(array $row, string $column): int
    {
        $value = $row[$column] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value) || preg_match('/\A[0-9]+\z/', $value) !== 1) {
            throw new UnexpectedValueException('Authentication persistence row is invalid.');
        }

        return (int)$value;
    }
}
