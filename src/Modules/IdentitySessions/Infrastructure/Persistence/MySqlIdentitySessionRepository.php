<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Infrastructure\Persistence;

use DateTimeImmutable;
use PDO;
use PDOException;
use Qmdb\Modules\Identity\Domain\AccountStatus;
use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\IdentitySessions\Domain\DeviceId;
use Qmdb\Modules\IdentitySessions\Domain\DeviceStatus;
use Qmdb\Modules\IdentitySessions\Domain\DeviceTokenHash;
use Qmdb\Modules\IdentitySessions\Domain\DuplicateLoginSubmissionException;
use Qmdb\Modules\IdentitySessions\Domain\LoginSubmissionId;
use Qmdb\Modules\IdentitySessions\Domain\Repository\DeviceAuthenticationRecord;
use Qmdb\Modules\IdentitySessions\Domain\Repository\DeviceInventoryRecord;
use Qmdb\Modules\IdentitySessions\Domain\Repository\SessionAuthenticationRecord;
use Qmdb\Modules\IdentitySessions\Domain\Repository\SessionInventoryRecord;
use Qmdb\Modules\IdentitySessions\Domain\Repository\UserDeviceRepository;
use Qmdb\Modules\IdentitySessions\Domain\Repository\UserSessionRepository;
use Qmdb\Modules\IdentitySessions\Domain\SessionId;
use Qmdb\Modules\IdentitySessions\Domain\SessionRevocationReason;
use Qmdb\Modules\IdentitySessions\Domain\SessionStatus;
use Qmdb\Modules\IdentitySessions\Domain\SessionTokenHash;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationMethod;
use Qmdb\Modules\IdentityMultiFactor\Domain\SessionAuthenticationAssurance;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use UnexpectedValueException;

final readonly class MySqlIdentitySessionRepository implements UserDeviceRepository, UserSessionRepository
{
    public function __construct(private DatabaseConnectionProvider $provider)
    {
    }

    public function findForAccount(int $accountInternalId, DeviceId $deviceId): ?DeviceAuthenticationRecord
    {
        $statement = $this->pdo()->prepare(
            'SELECT id, public_id, account_id, token_hash, status, version, created_at, last_seen_at '
            . 'FROM user_devices WHERE account_id = :account_id AND public_id = :public_id LIMIT 1',
        );
        $statement->bindValue(':account_id', $accountInternalId, PDO::PARAM_INT);
        $statement->bindValue(':public_id', $deviceId->toBinary(), PDO::PARAM_LOB);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->device(self::row($row)) : null;
    }

    public function createDevice(
        int $accountInternalId,
        DeviceId $deviceId,
        DeviceTokenHash $tokenHash,
        DateTimeImmutable $now,
    ): DeviceAuthenticationRecord {
        $statement = $this->pdo()->prepare(
            'INSERT INTO user_devices '
            . '(public_id, account_id, token_hash, status, version, created_at, last_seen_at, updated_at) '
            . "VALUES (:public_id, :account_id, :token_hash, 'ACTIVE', 1, :created_at, :last_seen_at, :updated_at)",
        );
        $statement->bindValue(':public_id', $deviceId->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':account_id', $accountInternalId, PDO::PARAM_INT);
        $statement->bindValue(':token_hash', $tokenHash->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':created_at', self::format($now));
        $statement->bindValue(':last_seen_at', self::format($now));
        $statement->bindValue(':updated_at', self::format($now));
        $statement->execute();

        return new DeviceAuthenticationRecord(
            (int)$this->pdo()->lastInsertId(),
            $deviceId,
            $accountInternalId,
            $tokenHash,
            DeviceStatus::ACTIVE,
            1,
            $now,
            $now,
        );
    }

    public function touchDevice(
        int $accountInternalId,
        int $deviceInternalId,
        int $expectedVersion,
        DateTimeImmutable $now,
    ): bool {
        $statement = $this->pdo()->prepare(
            "UPDATE user_devices SET last_seen_at = :last_seen_at, updated_at = :updated_at, version = version + 1 "
            . "WHERE id = :id AND account_id = :account_id AND version = :version AND status = 'ACTIVE'",
        );
        $statement->execute([
            ':last_seen_at' => self::format($now),
            ':updated_at' => self::format($now),
            ':id' => $deviceInternalId,
            ':account_id' => $accountInternalId,
            ':version' => $expectedVersion,
        ]);

        return $statement->rowCount() === 1;
    }

    public function revoke(
        int $accountInternalId,
        DeviceId $deviceId,
        int $expectedVersion,
        DateTimeImmutable $now,
    ): bool {
        $statement = $this->pdo()->prepare(
            "UPDATE user_devices SET status = 'REVOKED', revoked_at = :revoked_at, updated_at = :updated_at, "
            . 'version = version + 1 WHERE account_id = :account_id AND public_id = :public_id '
            . "AND version = :version AND status = 'ACTIVE'",
        );
        $statement->bindValue(':revoked_at', self::format($now));
        $statement->bindValue(':updated_at', self::format($now));
        $statement->bindValue(':account_id', $accountInternalId, PDO::PARAM_INT);
        $statement->bindValue(':public_id', $deviceId->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':version', $expectedVersion, PDO::PARAM_INT);
        $statement->execute();

        return $statement->rowCount() === 1;
    }

    public function listDevicesForAccount(int $accountInternalId, int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));
        $statement = $this->pdo()->prepare(
            'SELECT d.public_id, d.status, d.version, d.created_at, d.last_seen_at, '
            . "SUM(CASE WHEN s.status = 'ACTIVE' THEN 1 ELSE 0 END) AS active_session_count "
            . 'FROM user_devices d LEFT JOIN user_sessions s ON s.device_id = d.id AND s.account_id = d.account_id '
            . 'WHERE d.account_id = :account_id GROUP BY d.id, d.public_id, d.status, d.version, '
            . 'd.created_at, d.last_seen_at ORDER BY d.last_seen_at DESC, d.id DESC LIMIT ' . $limit,
        );
        $statement->execute([':account_id' => $accountInternalId]);
        $records = [];
        while (is_array($row = $statement->fetch(PDO::FETCH_ASSOC))) {
            $row = self::row($row);
            $records[] = new DeviceInventoryRecord(
                DeviceId::fromBinary(self::string($row, 'public_id'))->toString(),
                self::string($row, 'status'),
                self::integer($row, 'version'),
                self::string($row, 'created_at'),
                self::string($row, 'last_seen_at'),
                self::integer($row, 'active_session_count'),
            );
        }

        return $records;
    }

    public function findAuthenticationRecord(SessionId $sessionId): ?SessionAuthenticationRecord
    {
        $statement = $this->pdo()->prepare(
            'SELECT s.id, s.public_id, s.account_id, a.public_id AS account_public_id, '
            . 'a.account_status, s.device_id, d.public_id AS device_public_id, d.status AS device_status, '
            . 's.current_token_hash, s.previous_token_hash, s.previous_token_expires_at, s.status, s.version, '
            . 's.issued_at, s.authenticated_at, s.primary_authentication_method, '
            . 's.secondary_authentication_method, s.assurance_level, s.strong_authenticated_at, '
            . 's.last_seen_at, s.idle_expires_at, '
            . 's.absolute_expires_at, s.rotated_at FROM user_sessions s '
            . 'INNER JOIN user_accounts a ON a.id = s.account_id '
            . 'INNER JOIN user_devices d ON d.id = s.device_id AND d.account_id = s.account_id '
            . 'WHERE s.public_id = :public_id LIMIT 1',
        );
        $statement->bindValue(':public_id', $sessionId->toBinary(), PDO::PARAM_LOB);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->session(self::row($row)) : null;
    }

    public function loginSubmissionExists(LoginSubmissionId $submissionId): bool
    {
        $statement = $this->pdo()->prepare(
            'SELECT 1 FROM user_sessions WHERE login_submission_id = :submission_id LIMIT 1',
        );
        $statement->bindValue(':submission_id', $submissionId->toBinary(), PDO::PARAM_LOB);
        $statement->execute();

        return $statement->fetchColumn() !== false;
    }

    public function lockAccount(int $accountInternalId): void
    {
        $statement = $this->pdo()->prepare('SELECT id FROM user_accounts WHERE id = :id FOR UPDATE');
        $statement->execute([':id' => $accountInternalId]);
        if ($statement->fetchColumn() === false) {
            throw new UnexpectedValueException('Verified account no longer exists.');
        }
    }

    public function expireEffectiveSessions(int $accountInternalId, DateTimeImmutable $now): void
    {
        $statement = $this->pdo()->prepare(
            "UPDATE user_sessions SET status = 'EXPIRED', updated_at = :updated_at, version = version + 1 "
            . "WHERE account_id = :account_id AND status = 'ACTIVE' "
            . 'AND (idle_expires_at <= :idle_now OR absolute_expires_at <= :absolute_now)',
        );
        $statement->execute([
            ':updated_at' => self::format($now),
            ':account_id' => $accountInternalId,
            ':idle_now' => self::format($now),
            ':absolute_now' => self::format($now),
        ]);
    }

    public function revokeOldestForLimit(int $accountInternalId, int $keepCount, DateTimeImmutable $now): void
    {
        $statement = $this->pdo()->prepare(
            "SELECT id FROM user_sessions WHERE account_id = :account_id AND status = 'ACTIVE' "
            . 'ORDER BY issued_at DESC, id DESC FOR UPDATE',
        );
        $statement->execute([':account_id' => $accountInternalId]);
        $ids = [];
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $value) {
            $ids[] = self::integerValue($value);
        }
        $revokeIds = array_slice($ids, max(0, $keepCount));
        $revoke = $this->pdo()->prepare(
            "UPDATE user_sessions SET status = 'REVOKED', revoked_at = :revoked_at, "
            . "revoke_reason_code = 'SESSION_LIMIT', updated_at = :updated_at, version = version + 1 "
            . "WHERE id = :id AND account_id = :account_id AND status = 'ACTIVE'",
        );
        foreach ($revokeIds as $id) {
            $revoke->execute([
                ':revoked_at' => self::format($now),
                ':updated_at' => self::format($now),
                ':id' => $id,
                ':account_id' => $accountInternalId,
            ]);
        }
    }

    public function createSession(
        SessionId $sessionId,
        int $accountInternalId,
        int $deviceInternalId,
        LoginSubmissionId $submissionId,
        SessionTokenHash $tokenHash,
        DateTimeImmutable $now,
        DateTimeImmutable $idleExpiresAt,
        DateTimeImmutable $absoluteExpiresAt,
        ?SessionAuthenticationAssurance $assurance = null,
    ): void {
        $assurance ??= new SessionAuthenticationAssurance(
            AuthenticationMethod::PASSWORD,
            null,
            AuthenticationAssuranceLevel::PRIMARY,
            $now,
            null,
        );
        $statement = $this->pdo()->prepare(
            'INSERT INTO user_sessions (public_id, account_id, device_id, login_submission_id, '
            . 'current_token_hash, status, version, issued_at, authenticated_at, '
            . 'primary_authentication_method, secondary_authentication_method, assurance_level, '
            . 'strong_authenticated_at, last_seen_at, '
            . 'idle_expires_at, absolute_expires_at, rotated_at, updated_at) VALUES '
            . "(:public_id, :account_id, :device_id, :submission_id, :token_hash, 'ACTIVE', 1, "
            . ':issued_at, :authenticated_at, :primary_method, :secondary_method, :assurance_level, '
            . ':strong_authenticated_at, :last_seen_at, :idle_expires_at, '
            . ':absolute_expires_at, :rotated_at, :updated_at)',
        );
        $statement->bindValue(':public_id', $sessionId->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':account_id', $accountInternalId, PDO::PARAM_INT);
        $statement->bindValue(':device_id', $deviceInternalId, PDO::PARAM_INT);
        $statement->bindValue(':submission_id', $submissionId->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':token_hash', $tokenHash->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':primary_method', $assurance->primaryMethod->value);
        $statement->bindValue(':secondary_method', $assurance->secondaryMethod?->value);
        $statement->bindValue(':assurance_level', $assurance->level->value);
        $statement->bindValue(
            ':strong_authenticated_at',
            $assurance->strongAuthenticatedAt === null ? null : self::format($assurance->strongAuthenticatedAt),
        );
        foreach (['issued_at', 'authenticated_at', 'last_seen_at', 'rotated_at', 'updated_at'] as $name) {
            $statement->bindValue(':' . $name, self::format($now));
        }
        $statement->bindValue(':idle_expires_at', self::format($idleExpiresAt));
        $statement->bindValue(':absolute_expires_at', self::format($absoluteExpiresAt));
        try {
            $statement->execute();
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000' && $this->loginSubmissionExists($submissionId)) {
                throw new DuplicateLoginSubmissionException(
                    'Login submission already created a session.',
                    0,
                    $exception,
                );
            }
            throw $exception;
        }
    }

    public function rotate(
        int $sessionInternalId,
        int $expectedVersion,
        SessionTokenHash $previousHash,
        SessionTokenHash $newHash,
        DateTimeImmutable $previousExpiresAt,
        DateTimeImmutable $now,
    ): bool {
        $statement = $this->pdo()->prepare(
            'UPDATE user_sessions SET current_token_hash = :current_hash, previous_token_hash = :previous_hash, '
            . 'previous_token_expires_at = :previous_expires_at, rotated_at = :rotated_at, '
            . 'updated_at = :updated_at, version = version + 1 WHERE id = :id '
            . "AND version = :version AND status = 'ACTIVE'",
        );
        $statement->bindValue(':current_hash', $newHash->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':previous_hash', $previousHash->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':previous_expires_at', self::format($previousExpiresAt));
        $statement->bindValue(':rotated_at', self::format($now));
        $statement->bindValue(':updated_at', self::format($now));
        $statement->bindValue(':id', $sessionInternalId, PDO::PARAM_INT);
        $statement->bindValue(':version', $expectedVersion, PDO::PARAM_INT);
        $statement->execute();

        return $statement->rowCount() === 1;
    }

    public function touchSession(
        int $sessionInternalId,
        int $expectedVersion,
        DateTimeImmutable $lastSeenAt,
        DateTimeImmutable $idleExpiresAt,
    ): bool {
        $statement = $this->pdo()->prepare(
            'UPDATE user_sessions SET last_seen_at = :last_seen_at, idle_expires_at = :idle_expires_at, '
            . 'updated_at = :updated_at, version = version + 1 WHERE id = :id '
            . "AND version = :version AND status = 'ACTIVE'",
        );
        $statement->execute([
            ':last_seen_at' => self::format($lastSeenAt),
            ':idle_expires_at' => self::format($idleExpiresAt),
            ':updated_at' => self::format($lastSeenAt),
            ':id' => $sessionInternalId,
            ':version' => $expectedVersion,
        ]);

        return $statement->rowCount() === 1;
    }

    public function markExpired(int $sessionInternalId, int $expectedVersion, DateTimeImmutable $now): bool
    {
        $statement = $this->pdo()->prepare(
            "UPDATE user_sessions SET status = 'EXPIRED', updated_at = :updated_at, version = version + 1 "
            . "WHERE id = :id AND version = :version AND status = 'ACTIVE'",
        );
        $statement->execute([
            ':updated_at' => self::format($now),
            ':id' => $sessionInternalId,
            ':version' => $expectedVersion,
        ]);

        return $statement->rowCount() === 1;
    }

    public function revokeCurrent(
        int $accountInternalId,
        int $sessionInternalId,
        SessionRevocationReason $reason,
        DateTimeImmutable $now,
    ): bool {
        $statement = $this->pdo()->prepare(
            "UPDATE user_sessions SET status = 'REVOKED', revoked_at = :revoked_at, "
            . 'revoke_reason_code = :reason, updated_at = :updated_at, version = version + 1 '
            . "WHERE id = :id AND account_id = :account_id AND status = 'ACTIVE'",
        );
        $statement->execute([
            ':revoked_at' => self::format($now),
            ':reason' => $reason->value,
            ':updated_at' => self::format($now),
            ':id' => $sessionInternalId,
            ':account_id' => $accountInternalId,
        ]);

        return $statement->rowCount() === 1;
    }

    public function revokeOwned(
        int $accountInternalId,
        SessionId $sessionId,
        int $expectedVersion,
        SessionRevocationReason $reason,
        DateTimeImmutable $now,
    ): bool {
        $statement = $this->pdo()->prepare(
            "UPDATE user_sessions SET status = 'REVOKED', revoked_at = :revoked_at, "
            . 'revoke_reason_code = :reason, updated_at = :updated_at, version = version + 1 '
            . 'WHERE account_id = :account_id AND public_id = :public_id '
            . "AND version = :version AND status = 'ACTIVE'",
        );
        $statement->bindValue(':revoked_at', self::format($now));
        $statement->bindValue(':reason', $reason->value);
        $statement->bindValue(':updated_at', self::format($now));
        $statement->bindValue(':account_id', $accountInternalId, PDO::PARAM_INT);
        $statement->bindValue(':public_id', $sessionId->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':version', $expectedVersion, PDO::PARAM_INT);
        $statement->execute();

        return $statement->rowCount() === 1;
    }

    public function revokeForDevice(
        int $accountInternalId,
        int $deviceInternalId,
        SessionRevocationReason $reason,
        DateTimeImmutable $now,
    ): int {
        $statement = $this->pdo()->prepare(
            "UPDATE user_sessions SET status = 'REVOKED', revoked_at = :revoked_at, "
            . 'revoke_reason_code = :reason, updated_at = :updated_at, version = version + 1 '
            . "WHERE account_id = :account_id AND device_id = :device_id AND status = 'ACTIVE'",
        );
        $statement->execute([
            ':revoked_at' => self::format($now),
            ':reason' => $reason->value,
            ':updated_at' => self::format($now),
            ':account_id' => $accountInternalId,
            ':device_id' => $deviceInternalId,
        ]);

        return $statement->rowCount();
    }

    public function revokeAllForAccount(
        int $accountInternalId,
        SessionRevocationReason $reason,
        DateTimeImmutable $now,
    ): int {
        $statement = $this->pdo()->prepare(
            "UPDATE user_sessions SET status = 'REVOKED', revoked_at = :revoked_at, "
            . 'revoke_reason_code = :reason, updated_at = :updated_at, version = version + 1 '
            . "WHERE account_id = :account_id AND status = 'ACTIVE'",
        );
        $statement->execute([
            ':revoked_at' => self::format($now),
            ':reason' => $reason->value,
            ':updated_at' => self::format($now),
            ':account_id' => $accountInternalId,
        ]);

        return $statement->rowCount();
    }

    public function revokeOthersForAccount(
        int $accountInternalId,
        int $preservedSessionInternalId,
        SessionRevocationReason $reason,
        DateTimeImmutable $now,
    ): int {
        $statement = $this->pdo()->prepare(
            "UPDATE user_sessions SET status = 'REVOKED', revoked_at = :revoked_at, "
            . 'revoke_reason_code = :reason, updated_at = :updated_at, version = version + 1 '
            . "WHERE account_id = :account_id AND id <> :preserved_id AND status = 'ACTIVE'",
        );
        $statement->execute([
            ':revoked_at' => self::format($now),
            ':reason' => $reason->value,
            ':updated_at' => self::format($now),
            ':account_id' => $accountInternalId,
            ':preserved_id' => $preservedSessionInternalId,
        ]);

        return $statement->rowCount();
    }

    public function listSessionsForAccount(int $accountInternalId, int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));
        $statement = $this->pdo()->prepare(
            'SELECT s.public_id, d.public_id AS device_public_id, s.status, s.version, s.issued_at, '
            . 's.last_seen_at, s.idle_expires_at, s.absolute_expires_at, '
            . 's.primary_authentication_method, s.secondary_authentication_method, '
            . 's.assurance_level, s.strong_authenticated_at FROM user_sessions s '
            . 'INNER JOIN user_devices d ON d.id = s.device_id AND d.account_id = s.account_id '
            . 'WHERE s.account_id = :account_id ORDER BY s.issued_at DESC, s.id DESC LIMIT ' . $limit,
        );
        $statement->execute([':account_id' => $accountInternalId]);
        $records = [];
        while (is_array($row = $statement->fetch(PDO::FETCH_ASSOC))) {
            $row = self::row($row);
            $records[] = new SessionInventoryRecord(
                SessionId::fromBinary(self::string($row, 'public_id'))->toString(),
                DeviceId::fromBinary(self::string($row, 'device_public_id'))->toString(),
                self::string($row, 'status'),
                self::integer($row, 'version'),
                self::string($row, 'issued_at'),
                self::string($row, 'last_seen_at'),
                self::string($row, 'idle_expires_at'),
                self::string($row, 'absolute_expires_at'),
                self::string($row, 'primary_authentication_method'),
                self::nullableString($row, 'secondary_authentication_method'),
                self::string($row, 'assurance_level'),
                self::nullableString($row, 'strong_authenticated_at'),
            );
        }

        return $records;
    }

    /** @param array<string, mixed> $row */
    private function device(array $row): DeviceAuthenticationRecord
    {
        return new DeviceAuthenticationRecord(
            self::integer($row, 'id'),
            DeviceId::fromBinary(self::string($row, 'public_id')),
            self::integer($row, 'account_id'),
            new DeviceTokenHash(self::string($row, 'token_hash')),
            DeviceStatus::from(self::string($row, 'status')),
            self::integer($row, 'version'),
            new DateTimeImmutable(self::string($row, 'created_at')),
            new DateTimeImmutable(self::string($row, 'last_seen_at')),
        );
    }

    /** @param array<string, mixed> $row */
    private function session(array $row): SessionAuthenticationRecord
    {
        $previous = $row['previous_token_hash'] ?? null;
        $previousExpiry = $row['previous_token_expires_at'] ?? null;

        return new SessionAuthenticationRecord(
            self::integer($row, 'id'),
            SessionId::fromBinary(self::string($row, 'public_id')),
            self::integer($row, 'account_id'),
            AccountId::fromBinary(self::string($row, 'account_public_id')),
            AccountStatus::from(self::string($row, 'account_status')),
            self::integer($row, 'device_id'),
            DeviceId::fromBinary(self::string($row, 'device_public_id')),
            DeviceStatus::from(self::string($row, 'device_status')),
            new SessionTokenHash(self::string($row, 'current_token_hash')),
            is_string($previous) ? new SessionTokenHash($previous) : null,
            is_string($previousExpiry) ? new DateTimeImmutable($previousExpiry) : null,
            SessionStatus::from(self::string($row, 'status')),
            self::integer($row, 'version'),
            new DateTimeImmutable(self::string($row, 'issued_at')),
            new DateTimeImmutable(self::string($row, 'authenticated_at')),
            new DateTimeImmutable(self::string($row, 'last_seen_at')),
            new DateTimeImmutable(self::string($row, 'idle_expires_at')),
            new DateTimeImmutable(self::string($row, 'absolute_expires_at')),
            new DateTimeImmutable(self::string($row, 'rotated_at')),
            new SessionAuthenticationAssurance(
                AuthenticationMethod::from(self::string($row, 'primary_authentication_method')),
                ($secondary = self::nullableString($row, 'secondary_authentication_method')) === null
                    ? null
                    : AuthenticationMethod::from($secondary),
                AuthenticationAssuranceLevel::from(self::string($row, 'assurance_level')),
                new DateTimeImmutable(self::string($row, 'authenticated_at')),
                ($strong = self::nullableString($row, 'strong_authenticated_at')) === null
                    ? null
                    : new DateTimeImmutable($strong),
            ),
        );
    }

    private function pdo(): PDO
    {
        return $this->provider->connection();
    }

    private static function format(DateTimeImmutable $value): string
    {
        return $value->format('Y-m-d H:i:s.u');
    }

    /** @param array<string, mixed> $row */
    private static function string(array $row, string $column): string
    {
        $value = $row[$column] ?? null;
        if (!is_string($value)) {
            throw new UnexpectedValueException('Identity-session persistence row is invalid.');
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
            throw new UnexpectedValueException('Identity-session persistence row is invalid.');
        }

        return (int)$value;
    }

    /** @param array<string, mixed> $row */
    private static function nullableString(array $row, string $column): ?string
    {
        $value = $row[$column] ?? null;
        if ($value === null || is_string($value)) {
            return $value;
        }

        throw new UnexpectedValueException('Identity-session persistence row is invalid.');
    }

    /**
     * @param array<mixed, mixed> $row
     * @return array<string, mixed>
     */
    private static function row(array $row): array
    {
        $normalized = [];
        foreach ($row as $column => $value) {
            if (!is_string($column)) {
                throw new UnexpectedValueException('Identity-session persistence row is invalid.');
            }
            $normalized[$column] = $value;
        }

        return $normalized;
    }

    private static function integerValue(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value) || preg_match('/\A[0-9]+\z/', $value) !== 1) {
            throw new UnexpectedValueException('Identity-session identifier is invalid.');
        }

        return (int)$value;
    }
}
