<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionPublication\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOStatement;
use Qmdb\Modules\CompetitionLive\Infrastructure\Persistence\CanonicalJson;
use Qmdb\Modules\CompetitionPublication\Application\CompetitionResultPublicationRepository;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Schema\State\PdoResultReader;

final readonly class MySqlCompetitionResultPublicationRepository implements CompetitionResultPublicationRepository
{
    public function __construct(private DatabaseConnectionProvider $connections)
    {
    }

    public function lockPublication(int $workspaceId, UuidV7 $publicationId): ?array
    {
        $statement = $this->statement('SELECT id,public_id,workspace_id,round_id,result_run_id,status,version FROM competition_result_publications WHERE workspace_id=:workspace_id AND public_id=:public_id FOR UPDATE');
        $statement->execute([':workspace_id' => $workspaceId, ':public_id' => $publicationId->toBinary()]);

        return $this->publication($statement->fetch(PDO::FETCH_ASSOC));
    }

    public function lockPublishedResultRun(int $workspaceId, UuidV7 $resultRunId): ?array
    {
        $statement = $this->statement("SELECT id,public_id,workspace_id,round_id,status,input_checksum_sha256,result_checksum_sha256 FROM competition_result_runs WHERE workspace_id=:workspace_id AND public_id=:public_id AND status='PUBLISHED' FOR UPDATE");
        $statement->execute([':workspace_id' => $workspaceId, ':public_id' => $resultRunId->toBinary()]);
        $row = self::row($statement->fetch(PDO::FETCH_ASSOC));
        if ($row === null) {
            return null;
        }

        return [
            'id' => PdoResultReader::integer($row, 'id'),
            'public_id' => UuidV7::fromBinary(PdoResultReader::string($row, 'public_id'))->toString(),
            'workspace_id' => PdoResultReader::integer($row, 'workspace_id'),
            'round_id' => PdoResultReader::integer($row, 'round_id'),
            'status' => PdoResultReader::string($row, 'status'),
            'input_checksum' => self::hash($row, 'input_checksum_sha256'),
            'result_checksum' => self::hash($row, 'result_checksum_sha256'),
        ];
    }

    public function completed(UuidV7 $submissionId, string $fingerprint): ?array
    {
        $statement = $this->statement('SELECT request_fingerprint,aggregate_public_id,result_status,version_after FROM competition_p7_operations WHERE submission_id=:submission_id FOR UPDATE');
        $statement->execute([':submission_id' => $submissionId->toBinary()]);
        $row = self::row($statement->fetch(PDO::FETCH_ASSOC));
        if ($row === null) {
            return null;
        }
        if (!hash_equals(PdoResultReader::string($row, 'request_fingerprint'), $fingerprint)) {
            throw new \DomainException('Publication submission conflicts with a prior request.');
        }

        return ['publication_id' => UuidV7::fromBinary(PdoResultReader::string($row, 'aggregate_public_id'))->toString(), 'status' => PdoResultReader::string($row, 'result_status'), 'version' => PdoResultReader::integer($row, 'version_after')];
    }

    public function prepare(array $resultRun, int $actorAccountId, DateTimeImmutable $now): array
    {
        $number = $this->nextPublicationNumber($resultRun['workspace_id'], $resultRun['round_id']);
        $time = self::time($now);
        $publicationId = UuidV7::generate();
        $insert = $this->statement("INSERT INTO competition_result_publications (public_id,workspace_id,round_id,result_run_id,publication_number,status,public_visibility,version,prepared_by_account_id,created_at,updated_at,prepared_at) VALUES (:public_id,:workspace_id,:round_id,:result_run_id,:publication_number,'PREPARED','PRIVATE',1,:prepared_by,:created_at,:updated_at,:prepared_at)");
        $insert->execute([':public_id' => $publicationId->toBinary(), ':workspace_id' => $resultRun['workspace_id'], ':round_id' => $resultRun['round_id'], ':result_run_id' => $resultRun['id'], ':publication_number' => $number, ':prepared_by' => $actorAccountId, ':created_at' => $time, ':updated_at' => $time, ':prepared_at' => $time]);
        $publication = ['id' => (int) $this->connections->connection()->lastInsertId(), 'public_id' => $publicationId->toString(), 'workspace_id' => $resultRun['workspace_id'], 'round_id' => $resultRun['round_id'], 'result_run_id' => $resultRun['id'], 'status' => 'PREPARED', 'version' => 1];
        $official = CanonicalJson::encode(['result_run_public_id' => $resultRun['public_id'], 'result_checksum_sha256' => bin2hex($resultRun['result_checksum'])]);
        $package = $this->statement("INSERT INTO competition_result_packages (public_id,workspace_id,publication_id,package_type,schema_version,canonical_json,package_sha256,source_result_run_sha256,source_score_set_sha256,appeal_lineage_sha256,created_by_account_id,created_at) VALUES (:public_id,:workspace_id,:publication_id,'OFFICIAL_PRIVATE',1,:canonical_json,:package_sha256,:result_sha256,:input_sha256,:appeal_sha256,:created_by,:created_at)");
        $package->execute([':public_id' => UuidV7::generate()->toBinary(), ':workspace_id' => $resultRun['workspace_id'], ':publication_id' => $publication['id'], ':canonical_json' => $official, ':package_sha256' => hash('sha256', $official, true), ':result_sha256' => $resultRun['result_checksum'], ':input_sha256' => $resultRun['input_checksum'], ':appeal_sha256' => hash('sha256', '', true), ':created_by' => $actorAccountId, ':created_at' => $time]);
        $link = $this->statement('UPDATE competition_result_publications SET package_id=:package_id WHERE id=:id AND workspace_id=:workspace_id');
        $link->execute([':package_id' => (int) $this->connections->connection()->lastInsertId(), ':id' => $publication['id'], ':workspace_id' => $publication['workspace_id']]);

        return $publication;
    }

    public function transition(array $publication, string $target, int $actorAccountId, DateTimeImmutable $now): bool
    {
        $time = self::time($now);
        $public = in_array($target, ['PROVISIONAL_PUBLISHED', 'FINALIZED'], true) ? 'PUBLIC' : 'PRIVATE';
        $current = in_array($target, ['PROVISIONAL_PUBLISHED', 'FINALIZED'], true) ? 1 : null;
        $projection = $public === 'PUBLIC' ? $this->ensurePublicProjection($publication, $actorAccountId, $now) : null;
        $statement = $this->statement("UPDATE competition_result_publications SET status=:status,public_visibility=:visibility,current_public_marker=:current_marker,projection_sha256=COALESCE(:projection_sha256,projection_sha256),public_from=CASE WHEN :public_status='PUBLIC' AND public_from IS NULL THEN :public_from ELSE public_from END,version=version+1,updated_at=:updated_at,published_by_account_id=CASE WHEN :published_status='PROVISIONAL_PUBLISHED' THEN :actor_published ELSE published_by_account_id END,finalized_by_account_id=CASE WHEN :finalized_status='FINALIZED' THEN :actor_finalized ELSE finalized_by_account_id END,withdrawn_by_account_id=CASE WHEN :withdrawn_status='WITHDRAWN' THEN :actor_withdrawn ELSE withdrawn_by_account_id END,provisional_published_at=CASE WHEN :provisional_status='PROVISIONAL_PUBLISHED' THEN :provisional_at ELSE provisional_published_at END,held_at=CASE WHEN :held_status='HELD' THEN :held_at ELSE held_at END,released_at=CASE WHEN :released_status='PROVISIONAL_PUBLISHED' AND status='HELD' THEN :released_at ELSE released_at END,finalized_at=CASE WHEN :finalized_time_status='FINALIZED' THEN :finalized_at ELSE finalized_at END,withdrawn_at=CASE WHEN :withdrawn_time_status='WITHDRAWN' THEN :withdrawn_at ELSE withdrawn_at END,superseded_at=CASE WHEN :superseded_status='SUPERSEDED' THEN :superseded_at ELSE superseded_at END,archived_at=CASE WHEN :archived_status='ARCHIVED' THEN :archived_at ELSE archived_at END WHERE id=:id AND workspace_id=:workspace_id AND status=:previous AND version=:version");
        $statement->execute([':status' => $target, ':visibility' => $public, ':current_marker' => $current, ':projection_sha256' => $projection, ':public_status' => $public, ':public_from' => $time, ':updated_at' => $time, ':published_status' => $target, ':actor_published' => $actorAccountId, ':finalized_status' => $target, ':actor_finalized' => $actorAccountId, ':withdrawn_status' => $target, ':actor_withdrawn' => $actorAccountId, ':provisional_status' => $target, ':provisional_at' => $time, ':held_status' => $target, ':held_at' => $time, ':released_status' => $target, ':released_at' => $time, ':finalized_time_status' => $target, ':finalized_at' => $time, ':withdrawn_time_status' => $target, ':withdrawn_at' => $time, ':superseded_status' => $target, ':superseded_at' => $time, ':archived_status' => $target, ':archived_at' => $time, ':id' => $publication['id'], ':workspace_id' => $publication['workspace_id'], ':previous' => $publication['status'], ':version' => $publication['version']]);

        if ($statement->rowCount() !== 1) {
            return false;
        }
        if ($public === 'PUBLIC') {
            $this->enqueuePublicProjection($publication, $now);
        }

        return true;
    }

    /** @param array{id:int,public_id:string,workspace_id:int,round_id:int,result_run_id:int,status:string,version:int} $publication */
    private function ensurePublicProjection(array $publication, int $actorAccountId, DateTimeImmutable $now): string
    {
        $statement = $this->statement('SELECT result_run.public_id AS result_run_public_id,result_run.result_checksum_sha256,result_run.input_checksum_sha256,row_record.public_id,row_record.rank_position,row_record.total_units,row_record.public_label FROM competition_result_runs result_run INNER JOIN competition_result_rows row_record ON row_record.workspace_id=result_run.workspace_id AND row_record.result_run_id=result_run.id WHERE result_run.workspace_id=:workspace_id AND result_run.id=:result_run_id ORDER BY row_record.rank_position ASC,row_record.id ASC');
        $statement->execute([':workspace_id' => $publication['workspace_id'], ':result_run_id' => $publication['result_run_id']]);
        $source = null;
        $rows = [];
        while (($row = self::row($statement->fetch(PDO::FETCH_ASSOC))) !== null) {
            $source ??= ['public_id' => UuidV7::fromBinary(PdoResultReader::string($row, 'result_run_public_id'))->toString(), 'result_checksum' => self::hash($row, 'result_checksum_sha256'), 'input_checksum' => self::hash($row, 'input_checksum_sha256')];
            $rows[] = ['result_row_public_id' => UuidV7::fromBinary(PdoResultReader::string($row, 'public_id'))->toString(), 'rank_position' => PdoResultReader::integer($row, 'rank_position'), 'total_units' => PdoResultReader::integer($row, 'total_units'), 'public_label' => PdoResultReader::string($row, 'public_label')];
        }
        if ($source === null || $rows === []) {
            throw new \DomainException('Result publication cannot project an empty result run.');
        }
        $canonical = CanonicalJson::encode(['publication_public_id' => $publication['public_id'], 'result_run_public_id' => $source['public_id'], 'rows' => $rows]);
        $hash = hash('sha256', $canonical, true);
        $package = $this->statement("INSERT INTO competition_result_packages (public_id,workspace_id,publication_id,package_type,schema_version,canonical_json,package_sha256,source_result_run_sha256,source_score_set_sha256,appeal_lineage_sha256,created_by_account_id,created_at) VALUES (:public_id,:workspace_id,:publication_id,'PUBLIC_SAFE',1,:canonical_json,:package_sha256,:result_sha256,:input_sha256,:appeal_sha256,:created_by,:created_at) ON DUPLICATE KEY UPDATE id=id");
        $package->execute([':public_id' => UuidV7::generate()->toBinary(), ':workspace_id' => $publication['workspace_id'], ':publication_id' => $publication['id'], ':canonical_json' => $canonical, ':package_sha256' => $hash, ':result_sha256' => $source['result_checksum'], ':input_sha256' => $source['input_checksum'], ':appeal_sha256' => hash('sha256', '', true), ':created_by' => $actorAccountId, ':created_at' => self::time($now)]);

        return $hash;
    }

    /** @param array{id:int,public_id:string,workspace_id:int,round_id:int,result_run_id:int,status:string,version:int} $publication */
    private function enqueuePublicProjection(array $publication, DateTimeImmutable $now): void
    {
        $payload = CanonicalJson::encode(['publication_public_id' => $publication['public_id']]);
        $statement = $this->statement("INSERT INTO competition_p7_outbox_messages (public_id,workspace_id,live_session_id,live_event_public_id,message_type,payload_canonical_json,payload_sha256,status,attempts,available_at,lease_owner,lease_expires_at,delivered_at,dead_lettered_at,last_error_code,created_at,updated_at) VALUES (:public_id,:workspace_id,NULL,NULL,'RESULT_PUBLICATION_PROJECT',:payload,:payload_sha256,'PENDING',0,:now,NULL,NULL,NULL,NULL,NULL,:now,:now)");
        $statement->execute([
            ':public_id' => UuidV7::generate()->toBinary(),
            ':workspace_id' => $publication['workspace_id'],
            ':payload' => $payload,
            ':payload_sha256' => hash('sha256', $payload, true),
            ':now' => self::time($now),
        ]);
    }

    public function appendEvent(array $publication, string $eventType, ?int $actorAccountId, DateTimeImmutable $now): void
    {
        $statement = $this->statement('INSERT INTO competition_result_publication_events (public_id,workspace_id,publication_id,event_type,from_status,to_status,actor_type,actor_account_id,safe_reason_code,result_run_sha256,publication_sha256,correlation_id,occurred_at,created_at) SELECT :public_id,:workspace_id,p.id,:event_type,:from_status,:to_status,:actor_type,:actor_account_id,NULL,r.result_checksum_sha256,NULL,:correlation_id,:occurred_at,:created_at FROM competition_result_publications p INNER JOIN competition_result_runs r ON r.workspace_id=p.workspace_id AND r.id=p.result_run_id WHERE p.id=:publication_id AND p.workspace_id=:event_workspace_id');
        $statement->execute([':public_id' => UuidV7::generate()->toBinary(), ':workspace_id' => $publication['workspace_id'], ':event_type' => $eventType, ':from_status' => $publication['status'], ':to_status' => $eventType === 'PREPARED' ? 'PREPARED' : $this->targetForEvent($eventType), ':actor_type' => $actorAccountId === null ? 'SYSTEM' : 'ACCOUNT', ':actor_account_id' => $actorAccountId, ':correlation_id' => UuidV7::generate()->toBinary(), ':occurred_at' => self::time($now), ':created_at' => self::time($now), ':publication_id' => $publication['id'], ':event_workspace_id' => $publication['workspace_id']]);
    }

    public function record(UuidV7 $submissionId, string $fingerprint, string $operation, array $publication, string $status, int $versionAfter, DateTimeImmutable $now): void
    {
        $statement = $this->statement("INSERT INTO competition_p7_operations (public_id,submission_id,workspace_id,operation_code,request_fingerprint,aggregate_kind,aggregate_public_id,result_status,version_after,occurred_at) VALUES (:public_id,:submission_id,:workspace_id,:operation_code,:fingerprint,'RESULT_PUBLICATION',:aggregate_public_id,:result_status,:version_after,:occurred_at)");
        $statement->execute([':public_id' => UuidV7::generate()->toBinary(), ':submission_id' => $submissionId->toBinary(), ':workspace_id' => $publication['workspace_id'], ':operation_code' => $operation, ':fingerprint' => $fingerprint, ':aggregate_public_id' => UuidV7::fromString($publication['public_id'])->toBinary(), ':result_status' => $status, ':version_after' => $versionAfter, ':occurred_at' => self::time($now)]);
    }

    private function nextPublicationNumber(int $workspaceId, int $roundId): int
    {
        $statement = $this->statement('SELECT COALESCE(MAX(publication_number),0)+1 AS next_number FROM competition_result_publications WHERE workspace_id=:workspace_id AND round_id=:round_id FOR UPDATE');
        $statement->execute([':workspace_id' => $workspaceId, ':round_id' => $roundId]);
        $value = $statement->fetchColumn();
        if (!is_int($value) && !is_string($value)) {
            throw new \RuntimeException('Publication number could not be allocated.');
        }

        return (int) $value;
    }

    /** @return array{id:int,public_id:string,workspace_id:int,round_id:int,result_run_id:int,status:string,version:int}|null */
    private function publication(mixed $value): ?array
    {
        $row = self::row($value);
        if ($row === null) {
            return null;
        }

        return ['id' => PdoResultReader::integer($row, 'id'), 'public_id' => UuidV7::fromBinary(PdoResultReader::string($row, 'public_id'))->toString(), 'workspace_id' => PdoResultReader::integer($row, 'workspace_id'), 'round_id' => PdoResultReader::integer($row, 'round_id'), 'result_run_id' => PdoResultReader::integer($row, 'result_run_id'), 'status' => PdoResultReader::string($row, 'status'), 'version' => PdoResultReader::integer($row, 'version')];
    }

    private function targetForEvent(string $event): string
    {
        return match ($event) {
            'PROVISIONAL_PUBLISHED' => 'PROVISIONAL_PUBLISHED', 'HELD' => 'HELD', 'HOLD_RELEASED' => 'PROVISIONAL_PUBLISHED', 'FINALIZED' => 'FINALIZED', 'WITHDRAWN' => 'WITHDRAWN', 'SUPERSEDED' => 'SUPERSEDED', 'ARCHIVED' => 'ARCHIVED', default => throw new \InvalidArgumentException('Publication event is invalid.'),
        };
    }

    private function statement(string $sql): PDOStatement
    {
        $statement = $this->connections->connection()->prepare($sql);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Publication statement could not be prepared.');
        }

        return $statement;
    }

    /** @return array<string,mixed>|null */
    private static function row(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }
        foreach ($value as $key => $_) {
            if (!is_string($key)) {
                throw new \UnexpectedValueException('Publication row is invalid.');
            }
        }

        return $value;
    }

    /** @param array<string,mixed> $row */
    private static function hash(array $row, string $field): string
    {
        $value = PdoResultReader::string($row, $field);
        if (strlen($value) !== 32) {
            throw new \UnexpectedValueException('Publication checksum is invalid.');
        }

        return $value;
    }

    private static function time(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }
}
