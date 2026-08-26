<?php

declare(strict_types=1);

namespace Qmdb\Modules\Identity\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Qmdb\Modules\Identity\Domain\AccountAuthenticationRecord;
use Qmdb\Modules\Identity\Domain\AccountContactStatus;
use Qmdb\Modules\Identity\Domain\AccountStatus;
use Qmdb\Modules\Identity\Domain\Repository\AccountRepository;
use Qmdb\Modules\Identity\Domain\UserAccount;
use Qmdb\Modules\Identity\Domain\Value\AccountEmailId;
use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\Identity\Domain\Value\AccountPhoneId;
use Qmdb\Modules\Identity\Domain\Value\CredentialId;
use Qmdb\Modules\Identity\Domain\Value\LookupHash;
use Qmdb\Modules\Identity\Domain\Value\SensitivePasswordHash;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use UnexpectedValueException;

final readonly class MySqlAccountRepository implements AccountRepository
{
    private const string INSERT_EMAIL = 'INSERT INTO account_email_addresses '
        . '(public_id, user_account_id, email_ciphertext, encryption_key_id, lookup_hash, status_code, '
        . 'version, created_at, updated_at) VALUES '
        . '(:public_id, :account_id, :ciphertext, :key_id, :lookup_hash, :status_code, 1, :created_at, :updated_at)';

    private const string INSERT_PHONE = 'INSERT INTO account_phone_numbers '
        . '(public_id, user_account_id, phone_ciphertext, encryption_key_id, lookup_hash, status_code, '
        . 'version, created_at, updated_at) VALUES '
        . '(:public_id, :account_id, :ciphertext, :key_id, :lookup_hash, :status_code, 1, :created_at, :updated_at)';

    public function __construct(private DatabaseConnectionProvider $provider)
    {
    }

    public function create(UserAccount $account): int
    {
        $statement = $this->provider->connection()->prepare(
            'INSERT INTO user_accounts '
            . '(public_id, account_status, preferred_locale, preferred_time_zone, version, created_at, updated_at) '
            . 'VALUES (:public_id, :status, :locale, :time_zone, :version, :created_at, :updated_at)',
        );
        $statement->bindValue(':public_id', $account->publicId->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':status', $account->status->value);
        $statement->bindValue(':locale', $account->preferredLocale);
        $statement->bindValue(':time_zone', $account->preferredTimeZone);
        $statement->bindValue(':version', $account->version, PDO::PARAM_INT);
        $statement->bindValue(':created_at', self::format($account->createdAt));
        $statement->bindValue(':updated_at', self::format($account->updatedAt));
        $statement->execute();

        return (int) $this->provider->connection()->lastInsertId();
    }

    public function addEmail(
        int $accountInternalId,
        AccountEmailId $emailId,
        string $ciphertext,
        string $encryptionKeyId,
        LookupHash $lookupHash,
        AccountContactStatus $status,
        DateTimeImmutable $createdAt,
    ): int {
        return $this->insertContact(
            self::INSERT_EMAIL,
            $accountInternalId,
            $emailId->toBinary(),
            $ciphertext,
            $encryptionKeyId,
            $lookupHash,
            $status,
            $createdAt,
        );
    }

    public function addPhone(
        int $accountInternalId,
        AccountPhoneId $phoneId,
        string $ciphertext,
        string $encryptionKeyId,
        LookupHash $lookupHash,
        AccountContactStatus $status,
        DateTimeImmutable $createdAt,
    ): int {
        return $this->insertContact(
            self::INSERT_PHONE,
            $accountInternalId,
            $phoneId->toBinary(),
            $ciphertext,
            $encryptionKeyId,
            $lookupHash,
            $status,
            $createdAt,
        );
    }

    public function addPasswordCredential(
        int $accountInternalId,
        CredentialId $credentialId,
        SensitivePasswordHash $passwordHash,
        string $algorithm,
        int $metadataVersion,
        DateTimeImmutable $createdAt,
    ): int {
        $statement = $this->provider->connection()->prepare(
            'INSERT INTO account_credentials '
            . '(public_id, user_account_id, credential_type, credential_status, password_hash, algorithm, '
            . 'metadata_version, version, created_at, updated_at) VALUES '
            . '(:public_id, :account_id, :type, :status, :password_hash, :algorithm, '
            . ':metadata_version, 1, :created_at, :updated_at)',
        );
        $statement->bindValue(':public_id', $credentialId->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':account_id', $accountInternalId, PDO::PARAM_INT);
        $statement->bindValue(':type', 'PASSWORD');
        $statement->bindValue(':status', 'ACTIVE');
        $statement->bindValue(':password_hash', $passwordHash->revealForVerification());
        $statement->bindValue(':algorithm', $algorithm);
        $statement->bindValue(':metadata_version', $metadataVersion, PDO::PARAM_INT);
        $statement->bindValue(':created_at', self::format($createdAt));
        $statement->bindValue(':updated_at', self::format($createdAt));
        $statement->execute();

        return (int) $this->provider->connection()->lastInsertId();
    }

    public function authenticationByEmailHash(LookupHash $lookupHash): ?AccountAuthenticationRecord
    {
        $statement = $this->provider->connection()->prepare(
            'SELECT a.id, a.public_id, a.account_status, c.password_hash, c.algorithm, '
            . 'c.metadata_version, c.version AS credential_version FROM account_email_addresses e '
            . 'INNER JOIN user_accounts a ON a.id = e.user_account_id '
            . 'INNER JOIN account_credentials c ON c.user_account_id = a.id '
            . "AND c.credential_type = 'PASSWORD' AND c.credential_status = 'ACTIVE' "
            . 'WHERE e.active_lookup_hash = :lookup_hash LIMIT 1',
        );
        $statement->bindValue(':lookup_hash', $lookupHash->toBinary(), PDO::PARAM_LOB);
        $statement->execute();
        $row = self::associativeRow($statement->fetch(PDO::FETCH_ASSOC));
        if ($row === null) {
            return null;
        }
        return new AccountAuthenticationRecord(
            self::requiredInteger($row, 'id'),
            AccountId::fromBinary(self::requiredString($row, 'public_id')),
            AccountStatus::from(self::requiredString($row, 'account_status')),
            new SensitivePasswordHash(self::requiredString($row, 'password_hash')),
            self::requiredString($row, 'algorithm'),
            self::requiredInteger($row, 'metadata_version'),
            self::requiredInteger($row, 'credential_version'),
        );
    }

    public function activateVerifiedEmail(
        int $accountInternalId,
        int $emailInternalId,
        int $expectedAccountVersion,
        DateTimeImmutable $now,
    ): bool {
        $connection = $this->provider->connection();
        $email = $connection->prepare(
            "UPDATE account_email_addresses SET status_code = 'VERIFIED', verified_at = :verified_at, "
            . 'version = version + 1, updated_at = :updated_at '
            . "WHERE id = :email_id AND user_account_id = :account_id AND status_code = 'UNVERIFIED'",
        );
        $email->execute([
            'verified_at' => self::format($now),
            'updated_at' => self::format($now),
            'email_id' => $emailInternalId,
            'account_id' => $accountInternalId,
        ]);
        if ($email->rowCount() !== 1) {
            return false;
        }
        $account = $connection->prepare(
            "UPDATE user_accounts SET account_status = 'ACTIVE', version = version + 1, updated_at = :updated_at "
            . "WHERE id = :account_id AND account_status = 'PENDING_VERIFICATION' AND version = :expected_version",
        );
        $account->execute([
            'updated_at' => self::format($now),
            'account_id' => $accountInternalId,
            'expected_version' => $expectedAccountVersion,
        ]);

        return $account->rowCount() === 1;
    }

    public function replacePassword(
        int $accountInternalId,
        SensitivePasswordHash $passwordHash,
        string $algorithm,
        int $metadataVersion,
        int $expectedVersion,
        DateTimeImmutable $now,
    ): bool {
        $statement = $this->provider->connection()->prepare(
            'UPDATE account_credentials SET password_hash = :password_hash, algorithm = :algorithm, '
            . 'metadata_version = :metadata_version, version = version + 1, updated_at = :updated_at '
            . "WHERE user_account_id = :account_id AND credential_type = 'PASSWORD' "
            . "AND credential_status = 'ACTIVE' AND version = :expected_version",
        );
        $statement->execute([
            'password_hash' => $passwordHash->revealForVerification(),
            'algorithm' => $algorithm,
            'metadata_version' => $metadataVersion,
            'updated_at' => self::format($now),
            'account_id' => $accountInternalId,
            'expected_version' => $expectedVersion,
        ]);

        return $statement->rowCount() === 1;
    }

    private function insertContact(
        string $sql,
        int $accountInternalId,
        string $publicId,
        string $ciphertext,
        string $encryptionKeyId,
        LookupHash $lookupHash,
        AccountContactStatus $status,
        DateTimeImmutable $createdAt,
    ): int {
        $statement = $this->provider->connection()->prepare($sql);
        $statement->bindValue(':public_id', $publicId, PDO::PARAM_LOB);
        $statement->bindValue(':account_id', $accountInternalId, PDO::PARAM_INT);
        $statement->bindValue(':ciphertext', $ciphertext, PDO::PARAM_LOB);
        $statement->bindValue(':key_id', $encryptionKeyId);
        $statement->bindValue(':lookup_hash', $lookupHash->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':status_code', $status->value);
        $statement->bindValue(':created_at', self::format($createdAt));
        $statement->bindValue(':updated_at', self::format($createdAt));
        $statement->execute();

        return (int) $this->provider->connection()->lastInsertId();
    }

    private static function format(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }

    /** @return array<string, mixed>|null */
    private static function associativeRow(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }
        $row = [];
        foreach ($value as $column => $field) {
            if (!is_string($column)) {
                throw new UnexpectedValueException('Authentication persistence row has an invalid shape.');
            }
            $row[$column] = $field;
        }

        return $row;
    }

    /** @param array<string, mixed> $row */
    private static function requiredString(array $row, string $column): string
    {
        $value = $row[$column] ?? null;
        if (!is_string($value)) {
            throw new UnexpectedValueException('Authentication persistence row has an invalid shape.');
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
            throw new UnexpectedValueException('Authentication persistence row has an invalid shape.');
        }

        return (int) $value;
    }
}
