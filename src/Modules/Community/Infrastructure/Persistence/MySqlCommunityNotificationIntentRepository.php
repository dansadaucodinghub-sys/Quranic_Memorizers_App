<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOStatement;
use Qmdb\Modules\Community\Application\CommunityNotificationIntentRepository;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class MySqlCommunityNotificationIntentRepository implements CommunityNotificationIntentRepository
{
    public function __construct(private DatabaseConnectionProvider $connections)
    {
    }

    public function enqueue(
        int $workspaceId,
        int $recipientAccountId,
        string $typeCode,
        UuidV7 $subjectPublicId,
        int $subjectVersion,
        string $safeStateCode,
        DateTimeImmutable $now,
    ): void {
        $this->requireTransaction();
        if (preg_match('/\A[A-Z][A-Z0-9_]{1,47}\z/', $safeStateCode) !== 1) {
            throw new \InvalidArgumentException('Notification state code is invalid.');
        }
        $time = $this->time($now);
        $id = UuidV7::generate();
        $statement = $this->query(<<<'SQL'
INSERT IGNORE INTO community_notification_intents
 (public_id,workspace_id,recipient_account_id,type_code,subject_public_id,subject_version,status_code,
 safe_state_code,locale,attempt_count,next_attempt_at,created_at,updated_at)
VALUES (:public,:workspace,:recipient,:type,:subject,:version,'PENDING',:state,'en',0,:due,:created,:updated)
SQL, ['public' => $id->toBinary(), 'workspace' => $workspaceId, 'recipient' => $recipientAccountId,
            'type' => $typeCode, 'subject' => $subjectPublicId->toBinary(), 'version' => $subjectVersion,
            'state' => $safeStateCode, 'due' => $time, 'created' => $time, 'updated' => $time]);
        if ($statement->rowCount() === 1) {
            $this->event((int) $this->connections->connection()->lastInsertId(), 'CREATED', 0, null, $time);
        }
    }

    public function claimDue(UuidV7 $leaseOwner, DateTimeImmutable $now, DateTimeImmutable $leaseExpiresAt, int $limit): array
    {
        $this->requireTransaction();
        if ($limit < 1 || $limit > 100) {
            throw new \InvalidArgumentException('Notification batch size is invalid.');
        }
        $nowText = $this->time($now);
        $select = $this->connections->connection()->prepare("SELECT id FROM community_notification_intents WHERE status_code IN ('PENDING','RETRY','LEASED') AND next_attempt_at<=:now AND (lease_expires_at IS NULL OR lease_expires_at<=:expired) ORDER BY next_attempt_at,id LIMIT {$limit} FOR UPDATE SKIP LOCKED");
        if (!$select instanceof PDOStatement) {
            throw new \RuntimeException('Could not prepare notification claim.');
        }
        $select->execute(['now' => $nowText, 'expired' => $nowText]);
        $ids = $select->fetchAll(PDO::FETCH_COLUMN);
        $claimed = [];
        foreach ($ids as $idValue) {
            $id = is_int($idValue) || (is_string($idValue) && ctype_digit($idValue)) ? (int) $idValue : 0;
            if ($id < 1) {
                throw new \UnexpectedValueException('Notification identifier is malformed.');
            }
            $this->query(
                "UPDATE community_notification_intents SET status_code='LEASED',attempt_count=attempt_count+1,lease_owner=:owner,lease_expires_at=:expires,updated_at=:updated WHERE id=:id",
                ['owner' => $leaseOwner->toBinary(), 'expires' => $this->time($leaseExpiresAt),
                'updated' => $nowText,
                'id' => $id]
            );
            $row = $this->query(<<<'SQL'
SELECT n.id,n.public_id,n.recipient_account_id,n.type_code,n.subject_public_id,n.safe_state_code,
 n.locale,n.attempt_count,e.email_ciphertext,e.encryption_key_id
FROM community_notification_intents n
LEFT JOIN account_email_addresses e ON e.user_account_id=n.recipient_account_id AND e.status_code='VERIFIED'
WHERE n.id=:id ORDER BY e.verified_at DESC,e.id DESC LIMIT 1
SQL, ['id' => $id])->fetch(PDO::FETCH_ASSOC);
            if (
                !is_array($row) || !is_string($row['public_id'] ?? null)
                || !is_string($row['subject_public_id'] ?? null) || !is_string($row['type_code'] ?? null)
                || !is_string($row['safe_state_code'] ?? null) || !is_string($row['locale'] ?? null)
            ) {
                throw new \UnexpectedValueException('Notification claim row is malformed.');
            }
            $attempt = $this->integer($row, 'attempt_count');
            $this->event($id, 'CLAIMED', $attempt, null, $nowText);
            $claimed[] = ['id' => $id, 'public_id' => UuidV7::fromBinary($row['public_id']),
                'recipient_account_id' => $this->integer($row, 'recipient_account_id'),
                'type_code' => $row['type_code'],
                'subject_public_id' => UuidV7::fromBinary($row['subject_public_id']),
                'safe_state_code' => $row['safe_state_code'], 'locale' => $row['locale'],
                'attempt_count' => $attempt,
                'email_ciphertext' => is_string($row['email_ciphertext'] ?? null) ? $row['email_ciphertext'] : null,
                'email_key_id' => is_string($row['encryption_key_id'] ?? null) ? $row['encryption_key_id'] : null];
        }
        return $claimed;
    }

    public function delivered(int $id, UuidV7 $leaseOwner, DateTimeImmutable $now): bool
    {
        return $this->finish($id, $leaseOwner, 'DELIVERED', 'DELIVERED', null, null, $now);
    }

    public function retry(int $id, UuidV7 $leaseOwner, string $errorCode, DateTimeImmutable $nextAttempt, DateTimeImmutable $now): bool
    {
        return $this->finish($id, $leaseOwner, 'RETRY', 'RETRY_SCHEDULED', $errorCode, $nextAttempt, $now);
    }

    public function deadLetter(int $id, UuidV7 $leaseOwner, string $errorCode, DateTimeImmutable $now): bool
    {
        return $this->finish($id, $leaseOwner, 'DEAD_LETTER', 'DEAD_LETTERED', $errorCode, null, $now);
    }

    private function finish(
        int $id,
        UuidV7 $owner,
        string $status,
        string $event,
        ?string $error,
        ?DateTimeImmutable $next,
        DateTimeImmutable $now
    ): bool {
        $this->requireTransaction();
        $time = $this->time($now);
        $changed = $this->query(
            'UPDATE community_notification_intents SET status_code=:status,next_attempt_at=:next,lease_owner=NULL,lease_expires_at=NULL,delivered_at=:delivered,last_error_code=:error,updated_at=:updated WHERE id=:id AND status_code=\'LEASED\' AND lease_owner=:owner',
            ['status' => $status, 'next' => $next === null ? $time : $this->time($next),
                'delivered' => $status === 'DELIVERED' ? $time : null, 'error' => $error,
            'updated' => $time,
            'id' => $id,
            'owner' => $owner->toBinary()]
        );
        if ($changed->rowCount() !== 1) {
            return false;
        }
        $attempt = (int) $this->query(
            'SELECT attempt_count FROM community_notification_intents WHERE id=:id',
            ['id' => $id]
        )->fetchColumn();
        $this->event($id, $event, $attempt, $error, $time);
        return true;
    }

    private function event(int $intentId, string $event, int $attempt, ?string $error, string $time): void
    {
        $this->query(
            'INSERT INTO community_notification_events (public_id,intent_id,event_code,attempt_count,safe_error_code,created_at) VALUES (:public,:intent,:event,:attempt,:error,:created)',
            ['public' => UuidV7::generate()->toBinary(), 'intent' => $intentId, 'event' => $event,
            'attempt' => $attempt,
            'error' => $error,
            'created' => $time]
        );
    }

    /** @param array<string,int|string|null> $parameters */
    private function query(string $sql, array $parameters): PDOStatement
    {
        $statement = $this->connections->connection()->prepare($sql);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Could not prepare community notification statement.');
        }
        $statement->execute($parameters);
        return $statement;
    }

    /** @param array<array-key,mixed> $row */
    private function integer(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        return is_int($value) || (is_string($value) && ctype_digit($value))
            ? (int) $value : throw new \UnexpectedValueException('Notification row is malformed.');
    }

    private function requireTransaction(): void
    {
        if (!$this->connections->connection()->inTransaction()) {
            throw new \LogicException('Community notification operations require a transaction.');
        }
    }

    private function time(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }
}
