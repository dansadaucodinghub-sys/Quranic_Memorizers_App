<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySecurityNotifications\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotification;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationEventId;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationEventType;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationId;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationStatus;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\NotificationClaimExecutionId;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\Repository\AccountSecurityNotificationEventRepository;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\Repository\AccountSecurityNotificationRepository;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\SecurityNotificationDeduplicationKey;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use UnexpectedValueException;

final readonly class MySqlAccountSecurityNotificationRepository implements
    AccountSecurityNotificationRepository,
    AccountSecurityNotificationEventRepository
{
    public function __construct(private DatabaseConnectionProvider $provider)
    {
    }

    public function createPendingIntent(
        AccountSecurityNotificationId $id,
        int $accountInternalId,
        int $emailInternalId,
        AccountSecurityNotificationType $type,
        SecurityNotificationDeduplicationKey $deduplicationKey,
        string $locale,
        int $maximumAttempts,
        DateTimeImmutable $occurredAt,
    ): void {
        $statement = $this->pdo()->prepare(
            'INSERT INTO account_security_notifications '
            . '(public_id, account_id, account_email_address_id, notification_type, deduplication_key, '
            . 'locale, status, attempt_count, maximum_attempts, next_attempt_at, version, occurred_at, '
            . 'created_at, updated_at) VALUES (:public_id, :account_id, :email_id, :type, '
            . ":deduplication_key, :locale, 'PENDING', 0, :maximum_attempts, :next_attempt_at, "
            . '1, :occurred_at, :created_at, :updated_at)',
        );
        $statement->bindValue(':public_id', $id->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':account_id', $accountInternalId, PDO::PARAM_INT);
        $statement->bindValue(':email_id', $emailInternalId, PDO::PARAM_INT);
        $statement->bindValue(':type', $type->value);
        $statement->bindValue(':deduplication_key', $deduplicationKey->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':locale', $locale === 'ar' ? 'ar' : 'en');
        $statement->bindValue(':maximum_attempts', $maximumAttempts, PDO::PARAM_INT);
        foreach (['next_attempt_at', 'occurred_at', 'created_at', 'updated_at'] as $parameter) {
            $statement->bindValue(':' . $parameter, self::format($occurredAt));
        }
        $statement->execute();
        $this->appendEvent(
            (int)$this->pdo()->lastInsertId(),
            AccountSecurityNotificationEventType::CREATED,
            $occurredAt,
        );
    }

    public function claimDue(
        NotificationClaimExecutionId $executionId,
        DateTimeImmutable $now,
        DateTimeImmutable $leaseExpiresAt,
        int $limit,
    ): array {
        $limit = max(1, min(100, $limit));
        $select = $this->pdo()->prepare(
            'SELECT id, public_id, account_id, account_email_address_id, notification_type, '
            . 'deduplication_key, locale, status, attempt_count, maximum_attempts, next_attempt_at, '
            . 'claim_execution_id, lease_expires_at, version, occurred_at '
            . 'FROM account_security_notifications WHERE '
            . "(status = 'PENDING' AND next_attempt_at <= :now) OR "
            . "(status = 'CLAIMED' AND lease_expires_at <= :lease_now) "
            . 'ORDER BY next_attempt_at, id LIMIT ' . $limit . ' FOR UPDATE SKIP LOCKED',
        );
        $select->execute([':now' => self::format($now), ':lease_now' => self::format($now)]);
        $claimed = [];
        while (is_array($raw = $select->fetch(PDO::FETCH_ASSOC))) {
            $row = self::row($raw);
            $attempt = self::integer($row, 'attempt_count') + 1;
            $update = $this->pdo()->prepare(
                "UPDATE account_security_notifications SET status = 'CLAIMED', attempt_count = :attempt_count, "
                . 'claim_execution_id = :execution_id, claimed_at = :claimed_at, '
                . 'lease_expires_at = :lease_expires_at, failure_code = NULL, '
                . 'version = version + 1, updated_at = :updated_at WHERE id = :id AND version = :version',
            );
            $update->bindValue(':attempt_count', $attempt, PDO::PARAM_INT);
            $update->bindValue(':execution_id', $executionId->toBinary(), PDO::PARAM_LOB);
            $update->bindValue(':claimed_at', self::format($now));
            $update->bindValue(':lease_expires_at', self::format($leaseExpiresAt));
            $update->bindValue(':updated_at', self::format($now));
            $update->bindValue(':id', self::integer($row, 'id'), PDO::PARAM_INT);
            $update->bindValue(':version', self::integer($row, 'version'), PDO::PARAM_INT);
            $update->execute();
            if ($update->rowCount() !== 1) {
                continue;
            }
            $notification = new AccountSecurityNotification(
                self::integer($row, 'id'),
                AccountSecurityNotificationId::fromBinary(self::string($row, 'public_id')),
                self::integer($row, 'account_id'),
                self::integer($row, 'account_email_address_id'),
                AccountSecurityNotificationType::from(self::string($row, 'notification_type')),
                new SecurityNotificationDeduplicationKey(self::string($row, 'deduplication_key')),
                self::string($row, 'locale'),
                AccountSecurityNotificationStatus::CLAIMED,
                $attempt,
                self::integer($row, 'maximum_attempts'),
                new DateTimeImmutable(self::string($row, 'next_attempt_at')),
                $executionId,
                $leaseExpiresAt,
                self::integer($row, 'version') + 1,
                new DateTimeImmutable(self::string($row, 'occurred_at')),
            );
            $this->appendEvent(
                $notification->internalId,
                AccountSecurityNotificationEventType::CLAIMED,
                $now,
                $attempt,
                $executionId,
            );
            $claimed[] = $notification;
        }

        return $claimed;
    }

    public function verifiedRecipientCiphertext(AccountSecurityNotification $notification): ?string
    {
        $statement = $this->pdo()->prepare(
            'SELECT email_ciphertext FROM account_email_addresses '
            . "WHERE id = :email_id AND user_account_id = :account_id AND status_code = 'VERIFIED' LIMIT 1",
        );
        $statement->execute([
            ':email_id' => $notification->emailInternalId,
            ':account_id' => $notification->accountInternalId,
        ]);
        $value = $statement->fetchColumn();

        return is_string($value) ? $value : null;
    }

    public function markDelivered(
        AccountSecurityNotification $notification,
        NotificationClaimExecutionId $executionId,
        DateTimeImmutable $now,
    ): bool {
        return $this->terminalTransition(
            $notification,
            $executionId,
            'DELIVERED',
            'delivered_at',
            null,
            $now,
            AccountSecurityNotificationEventType::DELIVERED,
        );
    }

    public function scheduleRetry(
        AccountSecurityNotification $notification,
        NotificationClaimExecutionId $executionId,
        DateTimeImmutable $nextAttemptAt,
        string $failureCode,
        DateTimeImmutable $now,
    ): bool {
        $statement = $this->pdo()->prepare(
            "UPDATE account_security_notifications SET status = 'PENDING', next_attempt_at = :next_attempt_at, "
            . 'claim_execution_id = NULL, claimed_at = NULL, lease_expires_at = NULL, '
            . 'failure_code = :failure_code, version = version + 1, updated_at = :updated_at '
            . "WHERE id = :id AND status = 'CLAIMED' AND claim_execution_id = :execution_id "
            . 'AND version = :version',
        );
        $statement->bindValue(':next_attempt_at', self::format($nextAttemptAt));
        $statement->bindValue(':failure_code', $failureCode);
        $statement->bindValue(':updated_at', self::format($now));
        $statement->bindValue(':id', $notification->internalId, PDO::PARAM_INT);
        $statement->bindValue(':execution_id', $executionId->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':version', $notification->version, PDO::PARAM_INT);
        $statement->execute();
        if ($statement->rowCount() !== 1) {
            return false;
        }
        $this->appendEvent(
            $notification->internalId,
            AccountSecurityNotificationEventType::RETRY_SCHEDULED,
            $now,
            $notification->attemptCount,
            $executionId,
            $failureCode,
        );

        return true;
    }

    public function markFailed(
        AccountSecurityNotification $notification,
        NotificationClaimExecutionId $executionId,
        string $failureCode,
        DateTimeImmutable $now,
    ): bool {
        return $this->terminalTransition(
            $notification,
            $executionId,
            'FAILED',
            'failed_at',
            $failureCode,
            $now,
            AccountSecurityNotificationEventType::FAILED,
        );
    }

    public function appendEvent(
        int $notificationInternalId,
        AccountSecurityNotificationEventType $type,
        DateTimeImmutable $occurredAt,
        ?int $attemptNumber = null,
        ?NotificationClaimExecutionId $executionId = null,
        ?string $failureCode = null,
    ): void {
        $statement = $this->pdo()->prepare(
            'INSERT INTO account_security_notification_events '
            . '(public_id, notification_id, event_type, attempt_number, claim_execution_id, '
            . 'failure_code, occurred_at) VALUES (:public_id, :notification_id, :event_type, '
            . ':attempt_number, :execution_id, :failure_code, :occurred_at)',
        );
        $statement->bindValue(':public_id', AccountSecurityNotificationEventId::generate()->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':notification_id', $notificationInternalId, PDO::PARAM_INT);
        $statement->bindValue(':event_type', $type->value);
        $statement->bindValue(
            ':attempt_number',
            $attemptNumber,
            $attemptNumber === null ? PDO::PARAM_NULL : PDO::PARAM_INT,
        );
        $statement->bindValue(
            ':execution_id',
            $executionId?->toBinary(),
            $executionId === null ? PDO::PARAM_NULL : PDO::PARAM_LOB,
        );
        $statement->bindValue(':failure_code', $failureCode);
        $statement->bindValue(':occurred_at', self::format($occurredAt));
        $statement->execute();
    }

    private function terminalTransition(
        AccountSecurityNotification $notification,
        NotificationClaimExecutionId $executionId,
        string $status,
        string $timestampColumn,
        ?string $failureCode,
        DateTimeImmutable $now,
        AccountSecurityNotificationEventType $event,
    ): bool {
        if (!in_array($timestampColumn, ['delivered_at', 'failed_at'], true)) {
            throw new \LogicException('Security notification timestamp transition is invalid.');
        }
        $statement = $this->pdo()->prepare(
            'UPDATE account_security_notifications SET status = :status, ' . $timestampColumn . ' = :now, '
            . 'claim_execution_id = NULL, claimed_at = NULL, lease_expires_at = NULL, '
            . 'failure_code = :failure_code, version = version + 1, updated_at = :updated_at '
            . "WHERE id = :id AND status = 'CLAIMED' AND claim_execution_id = :execution_id "
            . 'AND version = :version',
        );
        $statement->bindValue(':status', $status);
        $statement->bindValue(':now', self::format($now));
        $statement->bindValue(':failure_code', $failureCode);
        $statement->bindValue(':updated_at', self::format($now));
        $statement->bindValue(':id', $notification->internalId, PDO::PARAM_INT);
        $statement->bindValue(':execution_id', $executionId->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':version', $notification->version, PDO::PARAM_INT);
        $statement->execute();
        if ($statement->rowCount() !== 1) {
            return false;
        }
        $this->appendEvent(
            $notification->internalId,
            $event,
            $now,
            $notification->attemptCount,
            $executionId,
            $failureCode,
        );

        return true;
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
                throw new UnexpectedValueException('Security notification persistence row is invalid.');
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
            throw new UnexpectedValueException('Security notification persistence row is invalid.');
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
            throw new UnexpectedValueException('Security notification persistence row is invalid.');
        }

        return (int)$value;
    }
}
