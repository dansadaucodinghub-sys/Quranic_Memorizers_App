<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionLive\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOStatement;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Time\Clock;

/**
 * Bounded P7 outbox delivery. Public projection snapshots intentionally derive
 * only session status and a sequence cursor, never private event payloads.
 */
final readonly class CompetitionP7LiveMaintenanceService
{
    public function __construct(private DatabaseConnectionProvider $connections, private Clock $clock)
    {
    }

    public function project(int $limit = 50, bool $dryRun = false): int
    {
        $this->assertLimit($limit);
        if ($dryRun) {
            return $this->countDue('LIVE_EVENT_PROJECT', $limit);
        }
        $worker = 'p7-projector-' . UuidV7::generate()->toString();
        $processed = 0;
        for ($index = 0; $index < $limit; ++$index) {
            $message = $this->claim($worker);
            if ($message === null) {
                break;
            }
            try {
                $this->deliver($message, $worker);
                ++$processed;
            } catch (\Throwable $error) {
                $this->retryOrDeadLetter($message['id'], $worker, $error);
            }
        }

        return $processed;
    }

    public function retryExpiredClaims(int $limit = 100, bool $dryRun = false): int
    {
        $this->assertLimit($limit);
        if ($dryRun) {
            $statement = $this->connections->connection()->prepare("SELECT COUNT(*) FROM (SELECT id FROM competition_p7_outbox_messages WHERE message_type='LIVE_EVENT_PROJECT' AND status='CLAIMED' AND lease_expires_at<=:expired_at ORDER BY lease_expires_at,id LIMIT {$limit}) AS expired_claims");
            if (!$statement instanceof PDOStatement) {
                throw new \RuntimeException('P7 outbox retry dry-run statement could not be prepared.');
            }
            $statement->execute([':expired_at' => $this->time($this->clock->now())]);

            return (int) $statement->fetchColumn();
        }
        $statement = $this->connections->connection()->prepare("UPDATE competition_p7_outbox_messages SET status='PENDING', lease_owner=NULL, lease_expires_at=NULL, available_at=:available_at, updated_at=:updated_at, last_error_code='LEASE_EXPIRED' WHERE status='CLAIMED' AND lease_expires_at<=:expired_at ORDER BY lease_expires_at,id LIMIT {$limit}");
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('P7 outbox retry statement could not be prepared.');
        }
        $now = $this->time($this->clock->now());
        $statement->execute([':available_at' => $now, ':updated_at' => $now, ':expired_at' => $now]);

        return $statement->rowCount();
    }

    /** Returns the number of checked authoritative live-event chains. */
    public function reconcile(int $limit = 100): int
    {
        $this->assertLimit($limit);
        $statement = $this->connections->connection()->prepare("SELECT id,workspace_id,public_id,head_sequence FROM competition_live_sessions ORDER BY updated_at,id LIMIT {$limit}");
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('P7 session reconciliation statement could not be prepared.');
        }
        $statement->execute();
        $checked = 0;
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            if (!is_array($row)) {
                throw new \RuntimeException('P7 session reconciliation row is invalid.');
            }
            $this->verifySessionChain($this->integer($row, 'id'), $this->integer($row, 'workspace_id'), $this->integer($row, 'head_sequence'));
            ++$checked;
        }

        return $checked;
    }

    /** @return array{id:int,workspace_id:int,live_session_id:int,live_event_public_id:string}|null */
    private function claim(string $worker): ?array
    {
        $pdo = $this->connections->connection();
        $pdo->beginTransaction();
        try {
            $now = $this->time($this->clock->now());
            $statement = $pdo->prepare("SELECT id,workspace_id,live_session_id,live_event_public_id FROM competition_p7_outbox_messages WHERE message_type='LIVE_EVENT_PROJECT' AND status='PENDING' AND available_at<=:now ORDER BY available_at,id LIMIT 1 FOR UPDATE SKIP LOCKED");
            if (!$statement instanceof PDOStatement) {
                throw new \RuntimeException('P7 outbox claim statement could not be prepared.');
            }
            $statement->execute([':now' => $now]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                $pdo->commit();

                return null;
            }
            $id = $this->integer($row, 'id');
            $update = $pdo->prepare("UPDATE competition_p7_outbox_messages SET status='CLAIMED',attempts=attempts+1,lease_owner=:worker,lease_expires_at=DATE_ADD(:lease_now,INTERVAL 60 SECOND),updated_at=:updated_at WHERE id=:id AND status='PENDING'");
            if (!$update instanceof PDOStatement) {
                throw new \RuntimeException('P7 outbox claim update could not be prepared.');
            }
            $update->execute([':worker' => $worker, ':lease_now' => $now, ':updated_at' => $now, ':id' => $id]);
            if ($update->rowCount() !== 1) {
                $pdo->rollBack();

                return null;
            }
            $pdo->commit();

            return ['id' => $id, 'workspace_id' => $this->integer($row, 'workspace_id'), 'live_session_id' => $this->integer($row, 'live_session_id'), 'live_event_public_id' => $this->binary($row, 'live_event_public_id')];
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    /** @param array{id:int,workspace_id:int,live_session_id:int,live_event_public_id:string} $message */
    private function deliver(array $message, string $worker): void
    {
        $pdo = $this->connections->connection();
        $pdo->beginTransaction();
        try {
            $messageCheck = $pdo->prepare("SELECT id FROM competition_p7_outbox_messages WHERE id=:id AND status='CLAIMED' AND lease_owner=:worker FOR UPDATE");
            if (!$messageCheck instanceof PDOStatement) {
                throw new \RuntimeException('P7 outbox delivery lock could not be prepared.');
            }
            $messageCheck->execute([':id' => $message['id'], ':worker' => $worker]);
            if ($messageCheck->fetchColumn() === false) {
                throw new \DomainException('P7 outbox delivery lease is unavailable.');
            }
            $event = $this->event($pdo, $message);
            $session = $this->session($pdo, $message);
            $this->advanceProjection($pdo, $message['workspace_id'], $message['live_session_id'], $session, $event);
            $now = $this->time($this->clock->now());
            $complete = $pdo->prepare("UPDATE competition_p7_outbox_messages SET status='DELIVERED',lease_owner=NULL,lease_expires_at=NULL,delivered_at=:delivered_at,updated_at=:updated_at,last_error_code=NULL WHERE id=:id AND status='CLAIMED' AND lease_owner=:worker");
            if (!$complete instanceof PDOStatement) {
                throw new \RuntimeException('P7 outbox completion statement could not be prepared.');
            }
            $complete->execute([':delivered_at' => $now, ':updated_at' => $now, ':id' => $message['id'], ':worker' => $worker]);
            if ($complete->rowCount() !== 1) {
                throw new \DomainException('P7 outbox completion lease is unavailable.');
            }
            $pdo->commit();
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    /** @param array{id:int,workspace_id:int,live_session_id:int,live_event_public_id:string} $message
     * @return array{sequence:int}
     */
    private function event(PDO $pdo, array $message): array
    {
        $statement = $pdo->prepare('SELECT sequence_number FROM competition_live_events WHERE workspace_id=:workspace_id AND live_session_id=:session_id AND public_id=:public_id FOR UPDATE');
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('P7 event projection statement could not be prepared.');
        }
        $statement->execute([':workspace_id' => $message['workspace_id'], ':session_id' => $message['live_session_id'], ':public_id' => $message['live_event_public_id']]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new \DomainException('P7 outbox source event is unavailable.');
        }

        return ['sequence' => $this->integer($row, 'sequence_number')];
    }

    /** @param array{id:int,workspace_id:int,live_session_id:int,live_event_public_id:string} $message
     * @return array{status:string,public_visibility:string}
     */
    private function session(PDO $pdo, array $message): array
    {
        $statement = $pdo->prepare('SELECT status,public_visibility FROM competition_live_sessions WHERE id=:id AND workspace_id=:workspace_id FOR UPDATE');
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('P7 session projection statement could not be prepared.');
        }
        $statement->execute([':id' => $message['live_session_id'], ':workspace_id' => $message['workspace_id']]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new \DomainException('P7 outbox session is unavailable.');
        }

        return ['status' => $this->string($row, 'status'), 'public_visibility' => $this->string($row, 'public_visibility')];
    }

    /** @param array{status:string,public_visibility:string} $session
     * @param array{sequence:int} $event
     */
    private function advanceProjection(PDO $pdo, int $workspaceId, int $sessionId, array $session, array $event): void
    {
        $stream = $this->stream($pdo, $workspaceId, $sessionId);
        $now = $this->time($this->clock->now());
        if ($event['sequence'] <= $stream['projected']) {
            return;
        }
        if ($event['sequence'] !== $stream['projected'] + 1) {
            throw new \DomainException('P7 projection event sequence has a gap.');
        }
        $snapshotHash = null;
        if ($session['public_visibility'] === 'PUBLIC') {
            $payload = CanonicalJson::encode(['sequence' => $event['sequence'], 'session_status' => $session['status']]);
            $snapshotHash = hash('sha256', $payload, true);
            $snapshot = $pdo->prepare('INSERT INTO competition_live_projection_snapshots (public_id,workspace_id,projection_stream_id,event_sequence,snapshot_schema_version,public_payload_canonical_json,snapshot_sha256,created_at) VALUES (:public_id,:workspace_id,:stream_id,:sequence,1,:payload,:sha256,:now)');
            if (!$snapshot instanceof PDOStatement) {
                throw new \RuntimeException('P7 projection snapshot statement could not be prepared.');
            }
            $snapshot->execute([':public_id' => UuidV7::generate()->toBinary(), ':workspace_id' => $workspaceId, ':stream_id' => $stream['id'], ':sequence' => $event['sequence'], ':payload' => $payload, ':sha256' => $snapshotHash, ':now' => $now]);
        }
        $update = $pdo->prepare('UPDATE competition_live_projection_streams SET head_event_sequence=GREATEST(head_event_sequence,:head_sequence),projected_event_sequence=:projected_sequence,current_snapshot_sha256=:snapshot_sha256,last_projected_at=:last_projected_at,updated_at=:updated_at,version=version+1 WHERE id=:id AND workspace_id=:workspace_id AND version=:version');
        if (!$update instanceof PDOStatement) {
            throw new \RuntimeException('P7 projection cursor statement could not be prepared.');
        }
        $update->execute([':head_sequence' => $event['sequence'], ':projected_sequence' => $event['sequence'], ':snapshot_sha256' => $snapshotHash, ':last_projected_at' => $now, ':updated_at' => $now, ':id' => $stream['id'], ':workspace_id' => $workspaceId, ':version' => $stream['version']]);
        if ($update->rowCount() !== 1) {
            throw new \DomainException('P7 projection stream changed concurrently.');
        }
    }

    /** @return array{id:int,projected:int,version:int} */
    private function stream(PDO $pdo, int $workspaceId, int $sessionId): array
    {
        $find = $pdo->prepare("SELECT id,projected_event_sequence,version FROM competition_live_projection_streams WHERE workspace_id=:workspace_id AND live_session_id=:session_id AND stream_code='PUBLIC' FOR UPDATE");
        if (!$find instanceof PDOStatement) {
            throw new \RuntimeException('P7 projection stream lookup could not be prepared.');
        }
        $find->execute([':workspace_id' => $workspaceId, ':session_id' => $sessionId]);
        $row = $find->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            $now = $this->time($this->clock->now());
            $insert = $pdo->prepare("INSERT INTO competition_live_projection_streams (public_id,workspace_id,live_session_id,stream_code,status,head_event_sequence,projected_event_sequence,snapshot_version,current_snapshot_sha256,transport_generation,last_projected_at,paused_at,recovery_started_at,recovered_at,closed_at,version,created_at,updated_at) VALUES (:public_id,:workspace_id,:session_id,'PUBLIC','ACTIVE',0,0,1,NULL,1,NULL,NULL,NULL,NULL,NULL,1,:created_at,:updated_at)");
            if (!$insert instanceof PDOStatement) {
                throw new \RuntimeException('P7 projection stream insert could not be prepared.');
            }
            $insert->execute([':public_id' => UuidV7::generate()->toBinary(), ':workspace_id' => $workspaceId, ':session_id' => $sessionId, ':created_at' => $now, ':updated_at' => $now]);
            $find->execute([':workspace_id' => $workspaceId, ':session_id' => $sessionId]);
            $row = $find->fetch(PDO::FETCH_ASSOC);
        }
        if (!is_array($row)) {
            throw new \RuntimeException('P7 projection stream could not be loaded.');
        }

        return ['id' => $this->integer($row, 'id'), 'projected' => $this->integer($row, 'projected_event_sequence'), 'version' => $this->integer($row, 'version')];
    }

    private function retryOrDeadLetter(int $id, string $worker, \Throwable $error): void
    {
        $now = $this->time($this->clock->now());
        $statement = $this->connections->connection()->prepare("UPDATE competition_p7_outbox_messages SET status=CASE WHEN attempts>=25 THEN 'DEAD_LETTER' ELSE 'PENDING' END,lease_owner=NULL,lease_expires_at=NULL,available_at=CASE WHEN attempts>=25 THEN available_at ELSE DATE_ADD(:available_now,INTERVAL LEAST(attempts * 5,300) SECOND) END,dead_lettered_at=CASE WHEN attempts>=25 THEN :dead_lettered_at ELSE NULL END,last_error_code=:error,updated_at=:updated_at WHERE id=:id AND status='CLAIMED' AND lease_owner=:worker");
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('P7 outbox retry update could not be prepared.');
        }
        $statement->execute([':available_now' => $now, ':dead_lettered_at' => $now, ':error' => substr($error::class, 0, 64), ':updated_at' => $now, ':id' => $id, ':worker' => $worker]);
    }

    private function countDue(string $messageType, int $limit): int
    {
        $statement = $this->connections->connection()->prepare("SELECT COUNT(*) FROM (SELECT id FROM competition_p7_outbox_messages WHERE message_type=:message_type AND status='PENDING' AND available_at<=:now ORDER BY available_at,id LIMIT {$limit}) AS due_messages");
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('P7 outbox dry-run statement could not be prepared.');
        }
        $statement->execute([':message_type' => $messageType, ':now' => $this->time($this->clock->now())]);

        return (int) $statement->fetchColumn();
    }

    private function verifySessionChain(int $sessionId, int $workspaceId, int $headSequence): void
    {
        $statement = $this->connections->connection()->prepare('SELECT sequence_number,event_sha256,previous_event_sha256 FROM competition_live_events WHERE workspace_id=:workspace_id AND live_session_id=:session_id ORDER BY sequence_number,id');
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('P7 event chain reconciliation statement could not be prepared.');
        }
        $statement->execute([':workspace_id' => $workspaceId, ':session_id' => $sessionId]);
        $previous = null;
        $expected = 1;
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            if (!is_array($row) || $this->integer($row, 'sequence_number') !== $expected || !is_string($row['event_sha256'] ?? null) || !is_string($row['previous_event_sha256'] ?? null) && $row['previous_event_sha256'] !== null) {
                throw new \DomainException('P7 event chain sequence is invalid.');
            }
            if ($previous !== null && !hash_equals($previous, $this->binary($row, 'previous_event_sha256'))) {
                throw new \DomainException('P7 event chain link is invalid.');
            }
            $previous = $this->binary($row, 'event_sha256');
            ++$expected;
        }
        if ($headSequence !== $expected - 1) {
            throw new \DomainException('P7 live session head sequence is invalid.');
        }
    }

    private function assertLimit(int $limit): void
    {
        if ($limit < 1 || $limit > 500) {
            throw new \InvalidArgumentException('P7 maintenance limit is invalid.');
        }
    }

    private function time(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }

    /** @param array<array-key,mixed> $row */
    private function integer(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        if (is_int($value) || (is_string($value) && preg_match('/^(?:0|[1-9][0-9]*)$/', $value) === 1)) {
            return (int) $value;
        }
        throw new \UnexpectedValueException('P7 maintenance integer is invalid.');
    }

    /** @param array<array-key,mixed> $row */
    private function string(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \UnexpectedValueException('P7 maintenance string is invalid.');
        }
        return $value;
    }

    /** @param array<array-key,mixed> $row */
    private function binary(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \UnexpectedValueException('P7 maintenance binary field is invalid.');
        }
        return $value;
    }
}
