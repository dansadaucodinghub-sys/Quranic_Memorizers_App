<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityRecovery\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Qmdb\Modules\Identity\Domain\AccountContactStatus;
use Qmdb\Modules\Identity\Domain\AccountStatus;
use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\Identity\Domain\Value\CredentialId;
use Qmdb\Modules\Identity\Domain\Value\LookupHash;
use Qmdb\Modules\IdentityAccess\Security\Password\PasswordHashResult;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryChallenge;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryChallengeId;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryChallengeStatus;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryEventId;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryEventType;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryTokenHash;
use Qmdb\Modules\IdentityRecovery\Domain\Repository\PasswordRecoveryChallengeRepository;
use Qmdb\Modules\IdentityRecovery\Domain\Repository\PasswordRecoveryEventRepository;
use Qmdb\Modules\IdentityRecovery\Domain\Repository\PasswordRecoveryTarget;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use UnexpectedValueException;

final readonly class MySqlPasswordRecoveryRepository implements
    PasswordRecoveryChallengeRepository,
    PasswordRecoveryEventRepository
{
    public function __construct(private DatabaseConnectionProvider $provider)
    {
    }

    public function targetByEmailHash(LookupHash $lookupHash): ?PasswordRecoveryTarget
    {
        $statement = $this->pdo()->prepare(
            'SELECT a.id AS account_id, a.public_id AS account_public_id, a.account_status, '
            . 'a.preferred_locale, e.id AS email_id, e.email_ciphertext, e.status_code, '
            . 'EXISTS(SELECT 1 FROM account_credentials c WHERE c.user_account_id = a.id '
            . "AND c.credential_type = 'PASSWORD' AND c.credential_status = 'ACTIVE') AS has_password "
            . 'FROM account_email_addresses e INNER JOIN user_accounts a ON a.id = e.user_account_id '
            . 'WHERE e.lookup_hash = :lookup_hash LIMIT 1',
        );
        $statement->bindValue(':lookup_hash', $lookupHash->toBinary(), PDO::PARAM_LOB);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        $row = self::row($row);

        return new PasswordRecoveryTarget(
            self::integer($row, 'account_id'),
            AccountId::fromBinary(self::string($row, 'account_public_id')),
            self::integer($row, 'email_id'),
            self::string($row, 'email_ciphertext'),
            AccountStatus::from(self::string($row, 'account_status')),
            AccountContactStatus::from(self::string($row, 'status_code')),
            self::string($row, 'preferred_locale'),
            self::integer($row, 'has_password') === 1,
        );
    }

    public function createPending(
        PasswordRecoveryTarget $target,
        PasswordRecoveryChallengeId $id,
        PasswordRecoveryTokenHash $tokenHash,
        string $locale,
        int $maximumAttempts,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $now,
    ): int {
        $statement = $this->pdo()->prepare(
            'INSERT INTO account_password_recovery_challenges '
            . '(public_id, account_id, account_email_address_id, token_hash, requested_locale, status, '
            . 'attempt_count, maximum_attempts, expires_at, version, created_at, updated_at) '
            . "VALUES (:public_id, :account_id, :email_id, :token_hash, :locale, 'PENDING', "
            . '0, :maximum_attempts, :expires_at, 1, :created_at, :updated_at)',
        );
        $statement->bindValue(':public_id', $id->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':account_id', $target->accountInternalId, PDO::PARAM_INT);
        $statement->bindValue(':email_id', $target->emailInternalId, PDO::PARAM_INT);
        $statement->bindValue(':token_hash', $tokenHash->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':locale', $locale === 'ar' ? 'ar' : 'en');
        $statement->bindValue(':maximum_attempts', $maximumAttempts, PDO::PARAM_INT);
        $statement->bindValue(':expires_at', self::format($expiresAt));
        $statement->bindValue(':created_at', self::format($now));
        $statement->bindValue(':updated_at', self::format($now));
        $statement->execute();

        return (int)$this->pdo()->lastInsertId();
    }

    public function findByPublicId(PasswordRecoveryChallengeId $id): ?PasswordRecoveryChallenge
    {
        return $this->challenge($id, false);
    }

    public function lockByPublicId(PasswordRecoveryChallengeId $id): ?PasswordRecoveryChallenge
    {
        return $this->challenge($id, true);
    }

    public function revokePendingForAccount(int $accountInternalId, DateTimeImmutable $now): int
    {
        $statement = $this->pdo()->prepare(
            "UPDATE account_password_recovery_challenges SET status = 'REVOKED', revoked_at = :now, "
            . 'version = version + 1, updated_at = :updated_at '
            . "WHERE account_id = :account_id AND status = 'PENDING'",
        );
        $statement->execute([
            ':now' => self::format($now),
            ':updated_at' => self::format($now),
            ':account_id' => $accountInternalId,
        ]);

        return $statement->rowCount();
    }

    public function incrementInvalidAttempt(PasswordRecoveryChallenge $challenge, DateTimeImmutable $now): void
    {
        $next = min($challenge->maximumAttempts, $challenge->attemptCount + 1);
        $revoked = $next >= $challenge->maximumAttempts;
        $statement = $this->pdo()->prepare(
            'UPDATE account_password_recovery_challenges SET attempt_count = :attempt_count, status = :status, '
            . 'revoked_at = :revoked_at, version = version + 1, updated_at = :updated_at '
            . "WHERE id = :id AND version = :version AND status = 'PENDING'",
        );
        $statement->execute([
            ':attempt_count' => $next,
            ':status' => $revoked ? 'REVOKED' : 'PENDING',
            ':revoked_at' => $revoked ? self::format($now) : null,
            ':updated_at' => self::format($now),
            ':id' => $challenge->internalId,
            ':version' => $challenge->version,
        ]);
    }

    public function markExpired(PasswordRecoveryChallenge $challenge, DateTimeImmutable $now): void
    {
        $statement = $this->pdo()->prepare(
            "UPDATE account_password_recovery_challenges SET status = 'EXPIRED', expired_at = :expired_at, "
            . 'version = version + 1, updated_at = :updated_at '
            . "WHERE id = :id AND version = :version AND status = 'PENDING'",
        );
        $statement->execute([
            ':expired_at' => self::format($now),
            ':updated_at' => self::format($now),
            ':id' => $challenge->internalId,
            ':version' => $challenge->version,
        ]);
    }

    public function markConsumed(PasswordRecoveryChallenge $challenge, DateTimeImmutable $now): bool
    {
        $statement = $this->pdo()->prepare(
            "UPDATE account_password_recovery_challenges SET status = 'CONSUMED', consumed_at = :consumed_at, "
            . 'version = version + 1, updated_at = :updated_at '
            . "WHERE id = :id AND version = :version AND status = 'PENDING'",
        );
        $statement->execute([
            ':consumed_at' => self::format($now),
            ':updated_at' => self::format($now),
            ':id' => $challenge->internalId,
            ':version' => $challenge->version,
        ]);

        return $statement->rowCount() === 1;
    }

    public function replacePasswordCredential(
        int $accountInternalId,
        PasswordHashResult $hash,
        DateTimeImmutable $now,
    ): void {
        $revoke = $this->pdo()->prepare(
            "UPDATE account_credentials SET credential_status = 'REVOKED', version = version + 1, "
            . "updated_at = :updated_at WHERE user_account_id = :account_id AND credential_type = 'PASSWORD' "
            . "AND credential_status = 'ACTIVE'",
        );
        $revoke->execute([':updated_at' => self::format($now), ':account_id' => $accountInternalId]);
        if ($revoke->rowCount() !== 1) {
            throw new UnexpectedValueException('Active password credential replacement failed.');
        }
        $insert = $this->pdo()->prepare(
            'INSERT INTO account_credentials (public_id, user_account_id, credential_type, credential_status, '
            . 'password_hash, algorithm, metadata_version, version, created_at, updated_at) '
            . "VALUES (:public_id, :account_id, 'PASSWORD', 'ACTIVE', :password_hash, :algorithm, "
            . ':metadata_version, 1, :created_at, :updated_at)',
        );
        $insert->bindValue(':public_id', CredentialId::generate()->toBinary(), PDO::PARAM_LOB);
        $insert->bindValue(':account_id', $accountInternalId, PDO::PARAM_INT);
        $insert->bindValue(':password_hash', $hash->hash->revealForPersistence());
        $insert->bindValue(':algorithm', $hash->algorithm);
        $insert->bindValue(':metadata_version', $hash->metadataVersion, PDO::PARAM_INT);
        $insert->bindValue(':created_at', self::format($now));
        $insert->bindValue(':updated_at', self::format($now));
        $insert->execute();
    }

    public function append(
        int $challengeInternalId,
        int $accountInternalId,
        PasswordRecoveryEventType $type,
        DateTimeImmutable $occurredAt,
        ?int $attemptNumber = null,
        ?string $failureCode = null,
        ?string $correlationId = null,
    ): void {
        $statement = $this->pdo()->prepare(
            'INSERT INTO account_password_recovery_events '
            . '(public_id, challenge_id, account_id, event_type, attempt_number, failure_code, '
            . 'correlation_id, occurred_at) VALUES '
            . '(:public_id, :challenge_id, :account_id, :event_type, :attempt_number, '
            . ':failure_code, :correlation_id, :occurred_at)',
        );
        $statement->bindValue(':public_id', PasswordRecoveryEventId::generate()->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':challenge_id', $challengeInternalId, PDO::PARAM_INT);
        $statement->bindValue(':account_id', $accountInternalId, PDO::PARAM_INT);
        $statement->bindValue(':event_type', $type->value);
        $statement->bindValue(
            ':attempt_number',
            $attemptNumber,
            $attemptNumber === null ? PDO::PARAM_NULL : PDO::PARAM_INT,
        );
        $statement->bindValue(':failure_code', $failureCode);
        $statement->bindValue(':correlation_id', $correlationId);
        $statement->bindValue(':occurred_at', self::format($occurredAt));
        $statement->execute();
    }

    public function listByChallenge(int $challengeInternalId, int $limit = 50): array
    {
        return $this->history('challenge_id', $challengeInternalId, $limit);
    }

    public function listByAccount(int $accountInternalId, int $limit = 50): array
    {
        return $this->history('account_id', $accountInternalId, $limit);
    }

    private function challenge(PasswordRecoveryChallengeId $id, bool $lock): ?PasswordRecoveryChallenge
    {
        $statement = $this->pdo()->prepare(
            'SELECT c.id, c.public_id, c.account_id, a.public_id AS account_public_id, '
            . 'c.account_email_address_id, a.account_status, e.status_code AS email_status, '
            . 'c.token_hash, c.requested_locale, c.status, c.attempt_count, c.maximum_attempts, '
            . 'c.expires_at, c.version FROM account_password_recovery_challenges c '
            . 'INNER JOIN user_accounts a ON a.id = c.account_id '
            . 'INNER JOIN account_email_addresses e '
            . 'ON e.user_account_id = c.account_id AND e.id = c.account_email_address_id '
            . 'WHERE c.public_id = :public_id LIMIT 1' . ($lock ? ' FOR UPDATE' : ''),
        );
        $statement->bindValue(':public_id', $id->toBinary(), PDO::PARAM_LOB);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        $row = self::row($row);

        return new PasswordRecoveryChallenge(
            self::integer($row, 'id'),
            PasswordRecoveryChallengeId::fromBinary(self::string($row, 'public_id')),
            self::integer($row, 'account_id'),
            AccountId::fromBinary(self::string($row, 'account_public_id')),
            self::integer($row, 'account_email_address_id'),
            AccountStatus::from(self::string($row, 'account_status')),
            AccountContactStatus::from(self::string($row, 'email_status')),
            new PasswordRecoveryTokenHash(self::string($row, 'token_hash')),
            self::string($row, 'requested_locale'),
            PasswordRecoveryChallengeStatus::from(self::string($row, 'status')),
            self::integer($row, 'attempt_count'),
            self::integer($row, 'maximum_attempts'),
            new DateTimeImmutable(self::string($row, 'expires_at')),
            self::integer($row, 'version'),
        );
    }

    /** @return list<array<string, int|string|null>> */
    private function history(string $column, int $id, int $limit): array
    {
        if (!in_array($column, ['challenge_id', 'account_id'], true)) {
            throw new \LogicException('Recovery history selector is invalid.');
        }
        $limit = max(1, min(100, $limit));
        $statement = $this->pdo()->prepare(
            'SELECT event_type, attempt_number, failure_code, correlation_id, occurred_at '
            . 'FROM account_password_recovery_events WHERE ' . $column . ' = :id '
            . 'ORDER BY occurred_at DESC, id DESC LIMIT ' . $limit,
        );
        $statement->execute([':id' => $id]);
        $rows = [];
        while (is_array($row = $statement->fetch(PDO::FETCH_ASSOC))) {
            $row = self::row($row);
            $rows[] = [
                'event_type' => self::string($row, 'event_type'),
                'attempt_number' => self::nullableInteger($row, 'attempt_number'),
                'failure_code' => self::nullableString($row, 'failure_code'),
                'correlation_id' => self::nullableString($row, 'correlation_id'),
                'occurred_at' => self::string($row, 'occurred_at'),
            ];
        }

        return $rows;
    }

    private function pdo(): PDO
    {
        return $this->provider->connection();
    }

    private static function format(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }

    /**
     * @param array<array-key, mixed> $row
     * @return array<string, mixed>
     */
    private static function row(array $row): array
    {
        $normalized = [];
        foreach ($row as $column => $value) {
            if (!is_string($column)) {
                throw new UnexpectedValueException('Password recovery persistence row is invalid.');
            }
            $normalized[$column] = $value;
        }

        return $normalized;
    }

    /** @param array<string, mixed> $row */
    private static function string(array $row, string $column): string
    {
        $value = $row[$column] ?? null;
        if (!is_string($value)) {
            throw new UnexpectedValueException('Password recovery persistence row is invalid.');
        }

        return $value;
    }

    /** @param array<string, mixed> $row */
    private static function integer(array $row, string $column): int
    {
        $value = $row[$column] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value) || preg_match('/\A[0-9]+\z/', $value) !== 1) {
            throw new UnexpectedValueException('Password recovery persistence row is invalid.');
        }

        return (int)$value;
    }

    /** @param array<string, mixed> $row */
    private static function nullableInteger(array $row, string $column): ?int
    {
        if (($row[$column] ?? null) === null) {
            return null;
        }

        return self::integer($row, $column);
    }

    /** @param array<string, mixed> $row */
    private static function nullableString(array $row, string $column): ?string
    {
        if (($row[$column] ?? null) === null) {
            return null;
        }

        return self::string($row, $column);
    }
}
