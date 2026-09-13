<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionLive\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOStatement;
use Qmdb\Modules\CompetitionLive\Application\CompetitionLiveRuntimeRepository;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Schema\State\PdoResultReader;

final readonly class MySqlCompetitionLiveRuntimeRepository implements CompetitionLiveRuntimeRepository
{
    public function __construct(private DatabaseConnectionProvider $connections)
    {
    }

    public function lockSession(int $workspaceId, UuidV7 $sessionPublicId): ?array
    {
        $statement = $this->connections->connection()->prepare('SELECT id, public_id, workspace_id, status, head_sequence, version FROM competition_live_sessions WHERE workspace_id=:workspace_id AND public_id=:public_id FOR UPDATE');
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Live-session lock statement could not be prepared.');
        }
        $statement->execute([':workspace_id' => $workspaceId, ':public_id' => $sessionPublicId->toBinary()]);
        $row = self::row($statement->fetch(PDO::FETCH_ASSOC));
        if ($row === null) {
            return null;
        }

        return [
            'id' => PdoResultReader::integer($row, 'id'),
            'public_id' => UuidV7::fromBinary(PdoResultReader::string($row, 'public_id'))->toString(),
            'workspace_id' => PdoResultReader::integer($row, 'workspace_id'),
            'status' => PdoResultReader::string($row, 'status'),
            'head_sequence' => PdoResultReader::integer($row, 'head_sequence'),
            'version' => PdoResultReader::integer($row, 'version'),
        ];
    }

    public function lockParticipant(int $workspaceId, UuidV7 $participantPublicId): ?array
    {
        $statement = $this->connections->connection()->prepare('SELECT id, public_id, workspace_id, live_session_id, current_state, call_sequence, state_version, version FROM competition_live_participant_states WHERE workspace_id=:workspace_id AND public_id=:public_id FOR UPDATE');
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Live-participant lock statement could not be prepared.');
        }
        $statement->execute([':workspace_id' => $workspaceId, ':public_id' => $participantPublicId->toBinary()]);
        $row = self::row($statement->fetch(PDO::FETCH_ASSOC));
        if ($row === null) {
            return null;
        }

        return [
            'id' => PdoResultReader::integer($row, 'id'),
            'public_id' => UuidV7::fromBinary(PdoResultReader::string($row, 'public_id'))->toString(),
            'workspace_id' => PdoResultReader::integer($row, 'workspace_id'),
            'live_session_id' => PdoResultReader::integer($row, 'live_session_id'),
            'current_state' => PdoResultReader::string($row, 'current_state'),
            'call_sequence' => PdoResultReader::integer($row, 'call_sequence'),
            'state_version' => PdoResultReader::integer($row, 'state_version'),
            'version' => PdoResultReader::integer($row, 'version'),
        ];
    }

    public function completed(UuidV7 $submissionId, string $requestFingerprint): ?array
    {
        $statement = $this->connections->connection()->prepare('SELECT request_fingerprint,result_status,version_after,sequence_after FROM competition_live_operations WHERE submission_id=:submission_id FOR UPDATE');
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Live-operation lookup statement could not be prepared.');
        }
        $statement->execute([':submission_id' => $submissionId->toBinary()]);
        $row = self::row($statement->fetch(PDO::FETCH_ASSOC));
        if ($row === null) {
            return null;
        }
        if (!hash_equals(PdoResultReader::string($row, 'request_fingerprint'), $requestFingerprint)) {
            throw new \DomainException('Live-operation submission conflicts with a prior request.');
        }

        return ['status' => PdoResultReader::string($row, 'result_status'), 'version' => PdoResultReader::integer($row, 'version_after'), 'sequence' => PdoResultReader::integer($row, 'sequence_after')];
    }

    public function transitionSession(array $session, string $targetStatus, DateTimeImmutable $now): bool
    {
        $time = self::time($now);
        $statement = $this->connections->connection()->prepare("UPDATE competition_live_sessions SET status=:status, version=version+1, updated_at=:now, opened_at=CASE WHEN :status='OPEN' AND opened_at IS NULL THEN :now ELSE opened_at END, paused_at=CASE WHEN :status='PAUSED' THEN :now ELSE paused_at END, recovery_started_at=CASE WHEN :status='RECOVERING' THEN :now ELSE recovery_started_at END, recovered_at=CASE WHEN :status='OPEN' AND status='RECOVERING' THEN :now ELSE recovered_at END, closed_at=CASE WHEN :status='CLOSED' THEN :now ELSE closed_at END, cancelled_at=CASE WHEN :status='CANCELLED' THEN :now ELSE cancelled_at END WHERE id=:id AND workspace_id=:workspace_id AND version=:version AND status=:previous");
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Live-session transition statement could not be prepared.');
        }
        $statement->execute([':status' => $targetStatus, ':now' => $time, ':id' => $session['id'], ':workspace_id' => $session['workspace_id'], ':version' => $session['version'], ':previous' => $session['status']]);

        return $statement->rowCount() === 1;
    }

    public function transitionParticipant(array $participant, string $targetState, DateTimeImmutable $now): bool
    {
        $time = self::time($now);
        $statement = $this->connections->connection()->prepare("UPDATE competition_live_participant_states SET current_state=:state, state_version=state_version+1, version=version+1, updated_at=:now, checked_in_at=CASE WHEN :state='CHECKED_IN' THEN :now ELSE checked_in_at END, called_at=CASE WHEN :state='CALLED' THEN :now ELSE called_at END, performance_started_at=CASE WHEN :state='PERFORMING' AND performance_started_at IS NULL THEN :now ELSE performance_started_at END, interrupted_at=CASE WHEN :state='INTERRUPTED' THEN :now ELSE interrupted_at END, completed_at=CASE WHEN :state='COMPLETED' THEN :now ELSE completed_at END, absent_at=CASE WHEN :state='ABSENT' THEN :now ELSE absent_at END, withdrawn_at=CASE WHEN :state='WITHDRAWN' THEN :now ELSE withdrawn_at END, disqualified_at=CASE WHEN :state='DISQUALIFIED' THEN :now ELSE disqualified_at END, cancelled_at=CASE WHEN :state='CANCELLED' THEN :now ELSE cancelled_at END WHERE id=:id AND workspace_id=:workspace_id AND version=:version AND current_state=:previous");
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Live-participant transition statement could not be prepared.');
        }
        $statement->execute([':state' => $targetState, ':now' => $time, ':id' => $participant['id'], ':workspace_id' => $participant['workspace_id'], ':version' => $participant['version'], ':previous' => $participant['current_state']]);

        return $statement->rowCount() === 1;
    }

    public function appendEvent(array $session, string $eventType, string $visibility, array $payload, ?int $actorAccountId, UuidV7 $correlationId, DateTimeImmutable $now): int
    {
        $sequence = $session['head_sequence'] + 1;
        $eventPublicId = UuidV7::generate();
        $payloadJson = CanonicalJson::encode($payload);
        $payloadHash = hash('sha256', $payloadJson, true);
        $prior = $session['head_sequence'] === 0 ? null : $this->previousEventHash($session['id'], $session['workspace_id'], $session['head_sequence']);
        $eventHash = hash('sha256', implode("\0", [UuidV7::fromString($session['public_id'])->toBinary(), (string) $sequence, $eventType, $visibility, $payloadHash, $prior ?? '', self::time($now), $correlationId->toBinary(), '1']), true);
        $statement = $this->connections->connection()->prepare('INSERT INTO competition_live_events (public_id,workspace_id,live_session_id,sequence_number,event_type,visibility,payload_schema_version,payload_canonical_json,payload_sha256,previous_event_sha256,event_sha256,actor_type,actor_account_id,idempotency_fingerprint,correlation_id,occurred_at,created_at) VALUES (:public_id,:workspace_id,:session_id,:sequence_number,:event_type,:visibility,1,:payload,:payload_sha256,:previous_sha256,:event_sha256,:actor_type,:actor_account_id,:idempotency,:correlation_id,:occurred_at,:created_at)');
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Live-event append statement could not be prepared.');
        }
        $statement->execute([':public_id' => $eventPublicId->toBinary(), ':workspace_id' => $session['workspace_id'], ':session_id' => $session['id'], ':sequence_number' => $sequence, ':event_type' => $eventType, ':visibility' => $visibility, ':payload' => $payloadJson, ':payload_sha256' => $payloadHash, ':previous_sha256' => $prior, ':event_sha256' => $eventHash, ':actor_type' => $actorAccountId === null ? 'SYSTEM' : 'ACCOUNT', ':actor_account_id' => $actorAccountId, ':idempotency' => hash('sha256', $correlationId->toBinary() . "\0" . $eventType, true), ':correlation_id' => $correlationId->toBinary(), ':occurred_at' => self::time($now), ':created_at' => self::time($now)]);
        $this->enqueueProjection($session, $eventPublicId, $sequence, $now);
        $advance = $this->connections->connection()->prepare('UPDATE competition_live_sessions SET head_sequence=:sequence WHERE id=:id AND workspace_id=:workspace_id AND head_sequence=:previous_sequence');
        if (!$advance instanceof PDOStatement) {
            throw new \RuntimeException('Live-event sequence update could not be prepared.');
        }
        $advance->execute([':sequence' => $sequence, ':id' => $session['id'], ':workspace_id' => $session['workspace_id'], ':previous_sequence' => $session['head_sequence']]);
        if ($advance->rowCount() !== 1) {
            throw new \DomainException('Live event sequence is stale.');
        }

        return $sequence;
    }

    /** @param array{id:int,public_id:string,workspace_id:int,status:string,head_sequence:int,version:int} $session */
    private function enqueueProjection(array $session, UuidV7 $eventPublicId, int $sequence, DateTimeImmutable $now): void
    {
        $payload = CanonicalJson::encode(['event_public_id' => $eventPublicId->toString(), 'sequence' => $sequence]);
        $statement = $this->connections->connection()->prepare("INSERT INTO competition_p7_outbox_messages (public_id,workspace_id,live_session_id,live_event_public_id,message_type,payload_canonical_json,payload_sha256,status,attempts,available_at,lease_owner,lease_expires_at,delivered_at,dead_lettered_at,last_error_code,created_at,updated_at) VALUES (:public_id,:workspace_id,:session_id,:event_public_id,'LIVE_EVENT_PROJECT',:payload,:payload_sha256,'PENDING',0,:now,NULL,NULL,NULL,NULL,NULL,:now,:now)");
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('P7 projection outbox statement could not be prepared.');
        }
        $statement->execute([':public_id' => UuidV7::generate()->toBinary(), ':workspace_id' => $session['workspace_id'], ':session_id' => $session['id'], ':event_public_id' => $eventPublicId->toBinary(), ':payload' => $payload, ':payload_sha256' => hash('sha256', $payload, true), ':now' => self::time($now)]);
    }

    public function record(UuidV7 $submissionId, string $requestFingerprint, string $operation, array $session, string $status, int $versionAfter, int $sequence, DateTimeImmutable $now): void
    {
        $statement = $this->connections->connection()->prepare('INSERT INTO competition_live_operations (public_id,submission_id,workspace_id,operation_code,request_fingerprint,aggregate_kind,aggregate_public_id,result_status,version_after,sequence_after,occurred_at) VALUES (:public_id,:submission_id,:workspace_id,:operation,:fingerprint,\'LIVE_SESSION\',:aggregate_public_id,:status,:version_after,:sequence_after,:occurred_at)');
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Live-operation receipt statement could not be prepared.');
        }
        $statement->execute([':public_id' => UuidV7::generate()->toBinary(), ':submission_id' => $submissionId->toBinary(), ':workspace_id' => $session['workspace_id'], ':operation' => $operation, ':fingerprint' => $requestFingerprint, ':aggregate_public_id' => UuidV7::fromString($session['public_id'])->toBinary(), ':status' => $status, ':version_after' => $versionAfter, ':sequence_after' => $sequence, ':occurred_at' => self::time($now)]);
    }

    public function recordParticipant(UuidV7 $submissionId, string $requestFingerprint, string $operation, array $participant, string $state, int $versionAfter, int $sequence, DateTimeImmutable $now): void
    {
        $statement = $this->connections->connection()->prepare('INSERT INTO competition_live_operations (public_id,submission_id,workspace_id,operation_code,request_fingerprint,aggregate_kind,aggregate_public_id,result_status,version_after,sequence_after,occurred_at) VALUES (:public_id,:submission_id,:workspace_id,:operation,:fingerprint,\'LIVE_PARTICIPANT\',:aggregate_public_id,:status,:version_after,:sequence_after,:occurred_at)');
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Live-participant operation receipt statement could not be prepared.');
        }
        $statement->execute([':public_id' => UuidV7::generate()->toBinary(), ':submission_id' => $submissionId->toBinary(), ':workspace_id' => $participant['workspace_id'], ':operation' => $operation, ':fingerprint' => $requestFingerprint, ':aggregate_public_id' => UuidV7::fromString($participant['public_id'])->toBinary(), ':status' => $state, ':version_after' => $versionAfter, ':sequence_after' => $sequence, ':occurred_at' => self::time($now)]);
    }

    private function previousEventHash(int $sessionId, int $workspaceId, int $sequence): string
    {
        $statement = $this->connections->connection()->prepare('SELECT event_sha256 FROM competition_live_events WHERE workspace_id=:workspace_id AND live_session_id=:session_id AND sequence_number=:sequence FOR UPDATE');
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Live-event hash lookup could not be prepared.');
        }
        $statement->execute([':workspace_id' => $workspaceId, ':session_id' => $sessionId, ':sequence' => $sequence]);
        $hash = $statement->fetchColumn();
        if (!is_string($hash) || strlen($hash) !== 32) {
            throw new \DomainException('Live-event chain is incomplete.');
        }

        return $hash;
    }

    private static function time(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }

    /** @return array<string,mixed>|null */
    private static function row(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }
        $row = [];
        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                throw new \RuntimeException('Live repository returned an invalid row.');
            }
            $row[$key] = $item;
        }

        return $row;
    }
}
