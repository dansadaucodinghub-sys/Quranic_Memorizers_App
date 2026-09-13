<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionPublication\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOStatement;
use Qmdb\Modules\CompetitionLive\Infrastructure\Persistence\CanonicalJson;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Time\Clock;

/**
 * Bounded consumer for result-publication outbox messages. It never changes a
 * P6 score, result run, or result row: it creates only public-safe immutable
 * snapshots and updates their governed current pointer.
 */
final readonly class CompetitionResultPublicationProjectionService
{
    public function __construct(private DatabaseConnectionProvider $connections, private Clock $clock)
    {
    }

    public function process(int $limit = 50, bool $dryRun = false): int
    {
        $this->assertLimit($limit);
        if ($dryRun) {
            return $this->countDue($limit);
        }

        $worker = 'p7-publication-projector-' . UuidV7::generate()->toString();
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

    /** Rebuilds current public snapshots only; dry-run never mutates a pointer. */
    public function rebuild(int $limit = 50, bool $dryRun = false): int
    {
        $this->assertLimit($limit);
        $statement = $this->statement("SELECT public_id FROM competition_result_publications WHERE current_public_marker=1 AND public_visibility='PUBLIC' AND status IN ('PROVISIONAL_PUBLISHED','FINALIZED') ORDER BY id LIMIT {$limit}");
        $statement->execute();
        $count = 0;
        while (($value = $statement->fetchColumn()) !== false) {
            if (!is_string($value) || strlen($value) !== 16) {
                throw new \UnexpectedValueException('Publication rebuild input is invalid.');
            }
            if ($dryRun) {
                $this->assertProjection(UuidV7::fromBinary($value)->toString(), false);
            } else {
                $this->project(UuidV7::fromBinary($value)->toString());
            }
            ++$count;
        }

        return $count;
    }

    /** Read-only integrity reconciliation; it repairs nothing. */
    public function reconcile(int $limit = 100): int
    {
        $this->assertLimit($limit);
        $statement = $this->statement("SELECT public_id FROM competition_result_publications WHERE current_public_marker=1 AND public_visibility='PUBLIC' AND status IN ('PROVISIONAL_PUBLISHED','FINALIZED') ORDER BY id LIMIT {$limit}");
        $statement->execute();
        $count = 0;
        while (($value = $statement->fetchColumn()) !== false) {
            if (!is_string($value) || strlen($value) !== 16) {
                throw new \UnexpectedValueException('Publication reconciliation input is invalid.');
            }
            $this->assertProjection(UuidV7::fromBinary($value)->toString(), true);
            ++$count;
        }

        return $count;
    }

    private function countDue(int $limit): int
    {
        $statement = $this->statement("SELECT COUNT(*) FROM (SELECT id FROM competition_p7_outbox_messages WHERE message_type='RESULT_PUBLICATION_PROJECT' AND status='PENDING' AND available_at<=:now ORDER BY available_at,id LIMIT {$limit}) AS due_messages");
        $statement->execute([':now' => $this->time($this->clock->now())]);

        return (int) $statement->fetchColumn();
    }

    /** @return array{id:int,workspace_id:int,publication_public_id:string}|null */
    private function claim(string $worker): ?array
    {
        $pdo = $this->connections->connection();
        $pdo->beginTransaction();
        try {
            $now = $this->time($this->clock->now());
            $find = $pdo->prepare("SELECT id,workspace_id,payload_canonical_json,payload_sha256 FROM competition_p7_outbox_messages WHERE message_type='RESULT_PUBLICATION_PROJECT' AND status='PENDING' AND available_at<=:now ORDER BY available_at,id LIMIT 1 FOR UPDATE SKIP LOCKED");
            if (!$find instanceof PDOStatement) {
                throw new \RuntimeException('Publication projection claim could not be prepared.');
            }
            $find->execute([':now' => $now]);
            $row = $find->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                $pdo->commit();

                return null;
            }
            $payload = $this->string($row, 'payload_canonical_json');
            if (!hash_equals($this->binary($row, 'payload_sha256'), hash('sha256', $payload, true))) {
                throw new \DomainException('Publication projection outbox checksum is invalid.');
            }
            $decoded = json_decode($payload, true, 16, JSON_THROW_ON_ERROR);
            if (!is_array($decoded) || !is_string($decoded['publication_public_id'] ?? null)) {
                throw new \DomainException('Publication projection outbox payload is invalid.');
            }
            $publicationId = UuidV7::fromString($decoded['publication_public_id'])->toString();
            $update = $pdo->prepare("UPDATE competition_p7_outbox_messages SET status='CLAIMED',attempts=attempts+1,lease_owner=:worker,lease_expires_at=DATE_ADD(:now,INTERVAL 60 SECOND),updated_at=:now WHERE id=:id AND status='PENDING'");
            if (!$update instanceof PDOStatement) {
                throw new \RuntimeException('Publication projection claim update could not be prepared.');
            }
            $update->execute([':worker' => $worker, ':now' => $now, ':id' => $this->integer($row, 'id')]);
            if ($update->rowCount() !== 1) {
                $pdo->rollBack();

                return null;
            }
            $pdo->commit();

            return ['id' => $this->integer($row, 'id'), 'workspace_id' => $this->integer($row, 'workspace_id'), 'publication_public_id' => $publicationId];
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    /** @param array{id:int,workspace_id:int,publication_public_id:string} $message */
    private function deliver(array $message, string $worker): void
    {
        $pdo = $this->connections->connection();
        $pdo->beginTransaction();
        try {
            $lease = $pdo->prepare("SELECT id FROM competition_p7_outbox_messages WHERE id=:id AND status='CLAIMED' AND lease_owner=:worker FOR UPDATE");
            if (!$lease instanceof PDOStatement) {
                throw new \RuntimeException('Publication projection delivery lock could not be prepared.');
            }
            $lease->execute([':id' => $message['id'], ':worker' => $worker]);
            if ($lease->fetchColumn() === false) {
                throw new \DomainException('Publication projection lease is unavailable.');
            }
            $this->project($message['publication_public_id'], $message['workspace_id'], $pdo);
            $now = $this->time($this->clock->now());
            $complete = $pdo->prepare("UPDATE competition_p7_outbox_messages SET status='DELIVERED',lease_owner=NULL,lease_expires_at=NULL,delivered_at=:now,updated_at=:now,last_error_code=NULL WHERE id=:id AND status='CLAIMED' AND lease_owner=:worker");
            if (!$complete instanceof PDOStatement) {
                throw new \RuntimeException('Publication projection completion could not be prepared.');
            }
            $complete->execute([':now' => $now, ':id' => $message['id'], ':worker' => $worker]);
            if ($complete->rowCount() !== 1) {
                throw new \DomainException('Publication projection completion lease is unavailable.');
            }
            $pdo->commit();
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    private function project(string $publicationPublicId, ?int $expectedWorkspaceId = null, ?PDO $transactionalPdo = null): void
    {
        $pdo = $transactionalPdo ?? $this->connections->connection();
        $ownsTransaction = $transactionalPdo === null;
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        try {
            $model = $this->projectionModel($pdo, $publicationPublicId, $expectedWorkspaceId, true);
            $head = $this->head($pdo, $model);
            if ($head !== null && hash_equals($head['sha256'], $model['sha256'])) {
                if ($ownsTransaction) {
                    $pdo->commit();
                }

                return;
            }
            $version = ($head['version'] ?? 0) + 1;
            $now = $this->time($this->clock->now());
            $insert = $pdo->prepare('INSERT INTO competition_result_publication_projections (public_id,workspace_id,publication_id,package_id,projection_version,public_payload_canonical_json,projection_sha256,source_result_run_sha256,created_at) VALUES (:public_id,:workspace_id,:publication_id,:package_id,:projection_version,:payload,:projection_sha256,:source_sha256,:created_at)');
            if (!$insert instanceof PDOStatement) {
                throw new \RuntimeException('Publication projection snapshot insert could not be prepared.');
            }
            $insert->execute([':public_id' => UuidV7::generate()->toBinary(), ':workspace_id' => $model['workspace_id'], ':publication_id' => $model['publication_id'], ':package_id' => $model['package_id'], ':projection_version' => $version, ':payload' => $model['payload'], ':projection_sha256' => $model['sha256'], ':source_sha256' => $model['source_sha256'], ':created_at' => $now]);
            $snapshotId = (int) $pdo->lastInsertId();
            if ($head === null) {
                $pointer = $pdo->prepare('INSERT INTO competition_result_publication_projection_heads (workspace_id,publication_id,current_projection_id,current_projection_sha256,cache_generation,version,updated_at) VALUES (:workspace_id,:publication_id,:projection_id,:sha256,1,1,:updated_at)');
                if (!$pointer instanceof PDOStatement) {
                    throw new \RuntimeException('Publication projection pointer insert could not be prepared.');
                }
                $pointer->execute([':workspace_id' => $model['workspace_id'], ':publication_id' => $model['publication_id'], ':projection_id' => $snapshotId, ':sha256' => $model['sha256'], ':updated_at' => $now]);
            } else {
                $pointer = $pdo->prepare('UPDATE competition_result_publication_projection_heads SET current_projection_id=:projection_id,current_projection_sha256=:sha256,cache_generation=cache_generation+1,version=version+1,updated_at=:updated_at WHERE id=:id AND workspace_id=:workspace_id AND version=:version');
                if (!$pointer instanceof PDOStatement) {
                    throw new \RuntimeException('Publication projection pointer update could not be prepared.');
                }
                $pointer->execute([':projection_id' => $snapshotId, ':sha256' => $model['sha256'], ':updated_at' => $now, ':id' => $head['id'], ':workspace_id' => $model['workspace_id'], ':version' => $head['version']]);
                if ($pointer->rowCount() !== 1) {
                    throw new \DomainException('Publication projection pointer changed concurrently.');
                }
            }
            $publication = $pdo->prepare('UPDATE competition_result_publications SET projection_sha256=:sha256,updated_at=:updated_at WHERE id=:id AND workspace_id=:workspace_id AND current_public_marker=1 AND public_visibility=\'PUBLIC\'');
            if (!$publication instanceof PDOStatement) {
                throw new \RuntimeException('Publication projection checksum update could not be prepared.');
            }
            $publication->execute([':sha256' => $model['sha256'], ':updated_at' => $now, ':id' => $model['publication_id'], ':workspace_id' => $model['workspace_id']]);
            if ($publication->rowCount() !== 1) {
                throw new \DomainException('Publication is no longer current while projection was being built.');
            }
            if ($ownsTransaction) {
                $pdo->commit();
            }
        } catch (\Throwable $error) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    private function assertProjection(string $publicationPublicId, bool $requireStored): void
    {
        $pdo = $this->connections->connection();
        $model = $this->projectionModel($pdo, $publicationPublicId, null, false);
        $head = $this->head($pdo, $model, false);
        if ($requireStored && ($head === null || !hash_equals($head['sha256'], $model['sha256']))) {
            throw new \DomainException('Publication projection is missing or inconsistent.');
        }
    }

    /** @return array{workspace_id:int,publication_id:int,package_id:int,payload:string,sha256:string,source_sha256:string} */
    private function projectionModel(PDO $pdo, string $publicationPublicId, ?int $expectedWorkspaceId, bool $lock): array
    {
        $sql = "SELECT p.id AS publication_id,p.workspace_id,p.result_run_id,p.status,p.public_visibility,p.current_public_marker,p.embargo_until,p.public_from,p.public_until,p.projection_sha256,r.status AS result_status,r.result_checksum_sha256,package_record.id AS package_id,package_record.canonical_json,package_record.package_sha256 FROM competition_result_publications p INNER JOIN competition_result_runs r ON r.workspace_id=p.workspace_id AND r.id=p.result_run_id INNER JOIN competition_result_packages package_record ON package_record.workspace_id=p.workspace_id AND package_record.publication_id=p.id AND package_record.package_type='PUBLIC_SAFE' WHERE p.public_id=:publication_public_id" . ($lock ? ' FOR UPDATE' : '');
        $statement = $pdo->prepare($sql);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Publication projection source lookup could not be prepared.');
        }
        $statement->execute([':publication_public_id' => UuidV7::fromString($publicationPublicId)->toBinary()]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new \DomainException('Public-safe publication source is unavailable.');
        }
        $workspaceId = $this->integer($row, 'workspace_id');
        if ($expectedWorkspaceId !== null && $expectedWorkspaceId !== $workspaceId) {
            throw new \DomainException('Publication projection tenant scope is invalid.');
        }
        $now = $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
        if (!in_array($this->string($row, 'status'), ['PROVISIONAL_PUBLISHED', 'FINALIZED'], true) || $this->string($row, 'public_visibility') !== 'PUBLIC' || $this->integer($row, 'current_public_marker') !== 1 || $this->string($row, 'result_status') !== 'PUBLISHED' || !$this->withinPublicWindow($row, $now)) {
            throw new \DomainException('Publication is not eligible for a public projection.');
        }
        $payload = $this->string($row, 'canonical_json');
        $hash = $this->binary($row, 'package_sha256');
        if (!hash_equals($hash, hash('sha256', $payload, true))) {
            throw new \DomainException('Public-safe result package checksum is invalid.');
        }
        $sourceHash = $this->binary($row, 'result_checksum_sha256');
        if (strlen($sourceHash) !== 32 || strlen($hash) !== 32) {
            throw new \UnexpectedValueException('Publication projection checksum length is invalid.');
        }

        return ['workspace_id' => $workspaceId, 'publication_id' => $this->integer($row, 'publication_id'), 'package_id' => $this->integer($row, 'package_id'), 'payload' => $payload, 'sha256' => $hash, 'source_sha256' => $sourceHash];
    }

    /** @param array{workspace_id:int,publication_id:int,package_id:int,payload:string,sha256:string,source_sha256:string} $model
     * @return array{id:int,sha256:string,version:int}|null
     */
    private function head(PDO $pdo, array $model, bool $lock = true): ?array
    {
        $statement = $pdo->prepare('SELECT id,current_projection_sha256,version FROM competition_result_publication_projection_heads WHERE workspace_id=:workspace_id AND publication_id=:publication_id' . ($lock ? ' FOR UPDATE' : ''));
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Publication projection pointer lookup could not be prepared.');
        }
        $statement->execute([':workspace_id' => $model['workspace_id'], ':publication_id' => $model['publication_id']]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return ['id' => $this->integer($row, 'id'), 'sha256' => $this->binary($row, 'current_projection_sha256'), 'version' => $this->integer($row, 'version')];
    }

    /** @param array<array-key,mixed> $row */
    private function withinPublicWindow(array $row, DateTimeImmutable $now): bool
    {
        $embargo = $row['embargo_until'] ?? null;
        if ($embargo !== null && (!is_string($embargo) || new DateTimeImmutable($embargo, new DateTimeZone('UTC')) > $now)) {
            return false;
        }
        $from = $row['public_from'] ?? null;
        if (!is_string($from) || new DateTimeImmutable($from, new DateTimeZone('UTC')) > $now) {
                return false;
        }
        $until = $row['public_until'] ?? null;

        return $until === null || (is_string($until) && new DateTimeImmutable($until, new DateTimeZone('UTC')) > $now);
    }

    private function retryOrDeadLetter(int $id, string $worker, \Throwable $error): void
    {
        $now = $this->time($this->clock->now());
        $statement = $this->statement("UPDATE competition_p7_outbox_messages SET status=CASE WHEN attempts>=25 THEN 'DEAD_LETTER' ELSE 'PENDING' END,lease_owner=NULL,lease_expires_at=NULL,available_at=CASE WHEN attempts>=25 THEN available_at ELSE DATE_ADD(:now,INTERVAL LEAST(attempts*5,300) SECOND) END,dead_lettered_at=CASE WHEN attempts>=25 THEN :now ELSE NULL END,last_error_code=:error,updated_at=:now WHERE id=:id AND status='CLAIMED' AND lease_owner=:worker");
        $statement->execute([':now' => $now, ':error' => substr($error::class, 0, 64), ':id' => $id, ':worker' => $worker]);
    }

    private function assertLimit(int $limit): void
    {
        if ($limit < 1 || $limit > 500) {
            throw new \InvalidArgumentException('Publication projection limit is invalid.');
        }
    }

    private function statement(string $sql): PDOStatement
    {
        $statement = $this->connections->connection()->prepare($sql);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Publication projection statement could not be prepared.');
        }

        return $statement;
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
        throw new \UnexpectedValueException('Publication projection integer is invalid.');
    }

    /** @param array<array-key,mixed> $row */
    private function string(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \UnexpectedValueException('Publication projection string is invalid.');
        }

        return $value;
    }

    /** @param array<array-key,mixed> $row */
    private function binary(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value) || strlen($value) !== 32) {
            throw new \UnexpectedValueException('Publication projection checksum is invalid.');
        }

        return $value;
    }
}
