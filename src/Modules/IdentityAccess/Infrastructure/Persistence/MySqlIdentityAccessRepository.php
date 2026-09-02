<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Qmdb\Modules\Identity\Domain\AccountContactStatus;
use Qmdb\Modules\Identity\Domain\AccountStatus;
use Qmdb\Modules\Identity\Domain\Value\LookupHash;
use Qmdb\Modules\IdentityAccess\Domain\EmailVerificationChallenge;
use Qmdb\Modules\IdentityAccess\Domain\EmailVerificationChallengeId;
use Qmdb\Modules\IdentityAccess\Domain\EmailVerificationChallengeStatus;
use Qmdb\Modules\IdentityAccess\Domain\EmailVerificationTokenHash;
use Qmdb\Modules\IdentityAccess\Domain\IdempotencyClaimStatus;
use Qmdb\Modules\IdentityAccess\Domain\IdempotencySubmissionId;
use Qmdb\Modules\IdentityAccess\Domain\PendingVerificationTarget;
use Qmdb\Modules\IdentityAccess\Domain\RegistrationSubmissionId;
use Qmdb\Modules\IdentityAccess\Domain\Repository\IdentityAccessRepository;
use Qmdb\Modules\IdentityAccess\Domain\VerificationPersistenceResult;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprint;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use UnexpectedValueException;

final readonly class MySqlIdentityAccessRepository implements IdentityAccessRepository
{
    public function __construct(private DatabaseConnectionProvider $provider)
    {
    }

    public function claimIdempotency(
        IdempotencySubmissionId $id,
        string $operation,
        IdentityFingerprint $fingerprint,
        DateTimeImmutable $now,
    ): IdempotencyClaimStatus {
        if (
            !in_array(
                $operation,
                [
                'ACCOUNT_REGISTRATION',
                'EMAIL_VERIFICATION_RESEND',
                'PASSWORD_RECOVERY_REQUEST',
                'PASSWORD_RECOVERY_RESET',
                'PERSON_PROFILE_CREATE',
                'PERSON_PROFILE_UPDATE',
                'PERSON_ROLE_ACTIVATE',
                'PERSON_ROLE_DEACTIVATE',
                'MEMORIZER_PROGRESS_UPDATE',
                'DEPENDENT_PROFILE_CREATE',
                'DEPENDENT_PROFILE_UPDATE',
                'GUARDIANSHIP_REVOKE',
                'ORGANIZATION_CREATE',
                'ORGANIZATION_UPDATE',
                'ORGANIZATION_RETIRE',
                'ORGANIZATION_UNIT_CREATE',
                'ORGANIZATION_UNIT_UPDATE',
                'ORGANIZATION_UNIT_RETIRE',
                'ORGANIZATION_AFFILIATION_REQUEST',
                'ORGANIZATION_AFFILIATION_ACCEPT',
                'ORGANIZATION_AFFILIATION_DECLINE',
                'ORGANIZATION_AFFILIATION_WITHDRAW',
                'ORGANIZATION_AFFILIATION_ASSIGNMENTS_UPDATE',
                'ORGANIZATION_AFFILIATION_SUSPEND',
                'ORGANIZATION_AFFILIATION_RESUME',
                'ORGANIZATION_AFFILIATION_END',
                'ORGANIZATION_AFFILIATION_LEAVE',
                ],
                true,
            )
        ) {
            throw new \InvalidArgumentException('Identity idempotency operation is invalid.');
        }
        $connection = $this->provider->connection();
        $insert = $connection->prepare(
            'INSERT IGNORE INTO identity_idempotency_records '
            . '(public_id, operation, request_fingerprint, created_at, completed_at) '
            . 'VALUES (:public_id, :operation, :fingerprint, :created_at, NULL)',
        );
        $insert->bindValue(':public_id', $id->toBinary(), PDO::PARAM_LOB);
        $insert->bindValue(':operation', $operation);
        $insert->bindValue(':fingerprint', $fingerprint->toBinary(), PDO::PARAM_LOB);
        $insert->bindValue(':created_at', self::format($now));
        $insert->execute();
        if ($insert->rowCount() === 1) {
            return IdempotencyClaimStatus::CLAIMED;
        }

        $select = $connection->prepare(
            'SELECT operation, request_fingerprint, completed_at FROM identity_idempotency_records '
            . 'WHERE public_id = :public_id FOR UPDATE',
        );
        $select->bindValue(':public_id', $id->toBinary(), PDO::PARAM_LOB);
        $select->execute();
        $row = self::requiredRow($select->fetch(PDO::FETCH_ASSOC));
        $same = hash_equals(self::requiredString($row, 'request_fingerprint'), $fingerprint->toBinary())
            && self::requiredString($row, 'operation') === $operation;

        return $same ? IdempotencyClaimStatus::REPLAY : IdempotencyClaimStatus::CONFLICT;
    }

    public function completeIdempotency(IdempotencySubmissionId $id, DateTimeImmutable $now): void
    {
        $statement = $this->provider->connection()->prepare(
            'UPDATE identity_idempotency_records SET completed_at = COALESCE(completed_at, :completed_at) '
            . 'WHERE public_id = :public_id',
        );
        $statement->bindValue(':completed_at', self::format($now));
        $statement->bindValue(':public_id', $id->toBinary(), PDO::PARAM_LOB);
        $statement->execute();
        if ($statement->rowCount() > 1) {
            throw new UnexpectedValueException('Identity idempotency completion is invalid.');
        }
    }

    public function historicalEmailExists(LookupHash $lookupHash): bool
    {
        $statement = $this->provider->connection()->prepare(
            'SELECT 1 FROM account_email_addresses WHERE lookup_hash = :lookup_hash LIMIT 1',
        );
        $statement->bindValue(':lookup_hash', $lookupHash->toBinary(), PDO::PARAM_LOB);
        $statement->execute();

        return $statement->fetchColumn() !== false;
    }

    public function pendingVerificationTarget(LookupHash $lookupHash): ?PendingVerificationTarget
    {
        $statement = $this->provider->connection()->prepare(
            'SELECT e.id AS email_id, e.user_account_id AS account_id FROM account_email_addresses e '
            . 'INNER JOIN user_accounts a ON a.id = e.user_account_id '
            . "WHERE e.lookup_hash = :lookup_hash AND e.status_code = 'UNVERIFIED' "
            . "AND a.account_status = 'PENDING_VERIFICATION' LIMIT 1",
        );
        $statement->bindValue(':lookup_hash', $lookupHash->toBinary(), PDO::PARAM_LOB);
        $statement->execute();
        $row = self::optionalRow($statement->fetch(PDO::FETCH_ASSOC));
        if ($row === null) {
            return null;
        }

        return new PendingVerificationTarget(
            self::requiredInteger($row, 'account_id'),
            self::requiredInteger($row, 'email_id'),
        );
    }

    public function revokePendingChallenges(int $emailInternalId, DateTimeImmutable $now): void
    {
        $statement = $this->provider->connection()->prepare(
            "UPDATE account_email_verification_challenges SET status = 'REVOKED', revoked_at = :revoked_at, "
            . 'version = version + 1, updated_at = :updated_at '
            . "WHERE account_email_address_id = :email_id AND status = 'PENDING'",
        );
        $formatted = self::format($now);
        $statement->bindValue(':revoked_at', $formatted);
        $statement->bindValue(':updated_at', $formatted);
        $statement->bindValue(':email_id', $emailInternalId, PDO::PARAM_INT);
        $statement->execute();
    }

    public function createChallenge(
        int $emailInternalId,
        EmailVerificationChallengeId $challengeId,
        EmailVerificationTokenHash $tokenHash,
        int $maximumAttempts,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $now,
    ): void {
        $statement = $this->provider->connection()->prepare(
            'INSERT INTO account_email_verification_challenges '
            . '(public_id, account_email_address_id, token_hash, status, attempt_count, maximum_attempts, '
            . 'expires_at, consumed_at, expired_at, revoked_at, version, created_at, updated_at) '
            . "VALUES (:public_id, :email_id, :token_hash, 'PENDING', 0, :maximum_attempts, "
            . ':expires_at, NULL, NULL, NULL, 1, :created_at, :updated_at)',
        );
        $statement->bindValue(':public_id', $challengeId->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':email_id', $emailInternalId, PDO::PARAM_INT);
        $statement->bindValue(':token_hash', $tokenHash->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':maximum_attempts', $maximumAttempts, PDO::PARAM_INT);
        $statement->bindValue(':expires_at', self::format($expiresAt));
        $statement->bindValue(':created_at', self::format($now));
        $statement->bindValue(':updated_at', self::format($now));
        $statement->execute();
    }

    public function lockChallenge(EmailVerificationChallengeId $challengeId): ?EmailVerificationChallenge
    {
        $statement = $this->provider->connection()->prepare(
            'SELECT c.id, c.public_id, c.account_email_address_id, e.user_account_id, a.version AS account_version, '
            . 'e.status_code AS email_status, a.account_status, c.token_hash, c.status, c.attempt_count, '
            . 'c.maximum_attempts, c.expires_at, c.version FROM account_email_verification_challenges c '
            . 'INNER JOIN account_email_addresses e ON e.id = c.account_email_address_id '
            . 'INNER JOIN user_accounts a ON a.id = e.user_account_id '
            . 'WHERE c.public_id = :public_id LIMIT 1 FOR UPDATE',
        );
        $statement->bindValue(':public_id', $challengeId->toBinary(), PDO::PARAM_LOB);
        $statement->execute();
        $row = self::optionalRow($statement->fetch(PDO::FETCH_ASSOC));
        if ($row === null) {
            return null;
        }

        return new EmailVerificationChallenge(
            self::requiredInteger($row, 'id'),
            EmailVerificationChallengeId::fromBinary(self::requiredString($row, 'public_id')),
            self::requiredInteger($row, 'account_email_address_id'),
            self::requiredInteger($row, 'user_account_id'),
            self::requiredInteger($row, 'account_version'),
            AccountContactStatus::from(self::requiredString($row, 'email_status')),
            AccountStatus::from(self::requiredString($row, 'account_status')),
            new EmailVerificationTokenHash(self::requiredString($row, 'token_hash')),
            EmailVerificationChallengeStatus::from(self::requiredString($row, 'status')),
            self::requiredInteger($row, 'attempt_count'),
            self::requiredInteger($row, 'maximum_attempts'),
            new DateTimeImmutable(self::requiredString($row, 'expires_at')),
            self::requiredInteger($row, 'version'),
        );
    }

    public function expireChallenge(EmailVerificationChallenge $challenge, DateTimeImmutable $now): void
    {
        $this->terminalUpdate($challenge, 'EXPIRED', 'expired_at', $now);
    }

    public function failChallenge(EmailVerificationChallenge $challenge, DateTimeImmutable $now): void
    {
        $next = min($challenge->maximumAttempts, $challenge->attemptCount + 1);
        $revoked = $next >= $challenge->maximumAttempts;
        $statement = $this->provider->connection()->prepare(
            'UPDATE account_email_verification_challenges SET attempt_count = :attempt_count, '
            . 'status = :status, revoked_at = :revoked_at, version = version + 1, updated_at = :updated_at '
            . "WHERE id = :id AND version = :version AND status = 'PENDING'",
        );
        $statement->bindValue(':attempt_count', $next, PDO::PARAM_INT);
        $statement->bindValue(':status', $revoked ? 'REVOKED' : 'PENDING');
        $statement->bindValue(':revoked_at', $revoked ? self::format($now) : null);
        $statement->bindValue(':updated_at', self::format($now));
        $statement->bindValue(':id', $challenge->internalId, PDO::PARAM_INT);
        $statement->bindValue(':version', $challenge->version, PDO::PARAM_INT);
        $statement->execute();
    }

    public function consumeChallenge(
        EmailVerificationChallenge $challenge,
        DateTimeImmutable $now,
    ): VerificationPersistenceResult {
        if ($challenge->status === EmailVerificationChallengeStatus::CONSUMED) {
            return $challenge->emailStatus === AccountContactStatus::VERIFIED
                && $challenge->accountStatus === AccountStatus::ACTIVE
                ? VerificationPersistenceResult::ALREADY_COMPLETED
                : VerificationPersistenceResult::INVALID;
        }
        if ($challenge->status !== EmailVerificationChallengeStatus::PENDING) {
            return VerificationPersistenceResult::INVALID;
        }
        $connection = $this->provider->connection();
        $challengeUpdate = $connection->prepare(
            "UPDATE account_email_verification_challenges SET status = 'CONSUMED', consumed_at = :consumed_at, "
            . 'version = version + 1, updated_at = :updated_at '
            . "WHERE id = :id AND version = :version AND status = 'PENDING'",
        );
        $formatted = self::format($now);
        $challengeUpdate->execute([
            'consumed_at' => $formatted,
            'updated_at' => $formatted,
            'id' => $challenge->internalId,
            'version' => $challenge->version,
        ]);
        if ($challengeUpdate->rowCount() !== 1) {
            return VerificationPersistenceResult::INVALID;
        }
        $email = $connection->prepare(
            "UPDATE account_email_addresses SET status_code = 'VERIFIED', verified_at = :verified_at, "
            . 'version = version + 1, updated_at = :updated_at '
            . "WHERE id = :email_id AND user_account_id = :account_id AND status_code = 'UNVERIFIED'",
        );
        $email->execute([
            'verified_at' => $formatted,
            'updated_at' => $formatted,
            'email_id' => $challenge->emailInternalId,
            'account_id' => $challenge->accountInternalId,
        ]);
        if ($email->rowCount() !== 1) {
            throw new UnexpectedValueException('Email verification state transition failed.');
        }
        $account = $connection->prepare(
            "UPDATE user_accounts SET account_status = 'ACTIVE', version = version + 1, updated_at = :now "
            . "WHERE id = :account_id AND account_status = 'PENDING_VERIFICATION' AND version = :version",
        );
        $account->execute([
            'now' => self::format($now),
            'account_id' => $challenge->accountInternalId,
            'version' => $challenge->accountVersion,
        ]);
        if ($account->rowCount() !== 1) {
            throw new UnexpectedValueException('Account activation state transition failed.');
        }
        $event = $connection->prepare(
            'INSERT INTO account_status_events '
            . '(user_account_id, event_type, occurred_at, payload_json, content_hash) '
            . "VALUES (:account_id, 'ACCOUNT_ACTIVATED_EMAIL_VERIFIED', :now, NULL, :content_hash)",
        );
        $event->bindValue(':account_id', $challenge->accountInternalId, PDO::PARAM_INT);
        $event->bindValue(':now', $formatted);
        $event->bindValue(
            ':content_hash',
            hash(
                'sha256',
                $challenge->accountInternalId . "\0ACCOUNT_ACTIVATED_EMAIL_VERIFIED\0" . $formatted,
                true,
            ),
            PDO::PARAM_LOB,
        );
        $event->execute();

        return VerificationPersistenceResult::COMPLETED;
    }

    private function terminalUpdate(
        EmailVerificationChallenge $challenge,
        string $status,
        string $timestampColumn,
        DateTimeImmutable $now,
    ): void {
        $sql = match ($timestampColumn) {
            'expired_at' => "UPDATE account_email_verification_challenges SET status = 'EXPIRED', "
                . 'expired_at = :expired_at, version = version + 1, updated_at = :updated_at '
                . "WHERE id = :id AND version = :version AND status = 'PENDING'",
            default => throw new \LogicException('Unsupported challenge terminal transition.'),
        };
        if ($status !== 'EXPIRED') {
            throw new \LogicException('Unsupported challenge terminal status.');
        }
        $statement = $this->provider->connection()->prepare($sql);
        $formatted = self::format($now);
        $statement->execute([
            'expired_at' => $formatted,
            'updated_at' => $formatted,
            'id' => $challenge->internalId,
            'version' => $challenge->version,
        ]);
    }

    private static function format(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }

    /** @return array<string, mixed> */
    private static function requiredRow(mixed $value): array
    {
        $row = self::optionalRow($value);
        if ($row === null) {
            throw new UnexpectedValueException('Identity-access persistence row is missing.');
        }

        return $row;
    }

    /** @return array<string, mixed>|null */
    private static function optionalRow(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }
        $row = [];
        foreach ($value as $column => $field) {
            if (!is_string($column)) {
                throw new UnexpectedValueException('Identity-access persistence row is invalid.');
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
            throw new UnexpectedValueException('Identity-access persistence row is invalid.');
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
            throw new UnexpectedValueException('Identity-access persistence row is invalid.');
        }

        return (int)$value;
    }
}
