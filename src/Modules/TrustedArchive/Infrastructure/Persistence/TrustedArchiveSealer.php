<?php

declare(strict_types=1);

namespace Qmdb\Modules\TrustedArchive\Infrastructure\Persistence;

use PDO;
use PDOStatement;
use Qmdb\Modules\TrustedArchive\Domain\TrustedArchiveHasher;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

/** Bounded, transactional archive sealing for issued certificate evidence. */
final readonly class TrustedArchiveSealer
{
    public function __construct(private DatabaseConnectionProvider $connections, private TrustedArchiveHasher $hasher)
    {
    }
    public function sealIssuedCertificates(int $limit = 100): int
    {
        if ($limit < 1 || $limit > 500) {
            throw new \InvalidArgumentException('Archive seal limit is invalid.');
        }$pdo = $this->connections->connection();
        $scan = $this->statement("SELECT c.id FROM certificates c WHERE c.status='ISSUED' AND NOT EXISTS (SELECT 1 FROM trusted_archive_records r WHERE r.record_kind='CERTIFICATE' AND r.source_public_id=c.public_id) ORDER BY c.id ASC LIMIT {$limit}");
        $scan->execute();
        $ids = $scan->fetchAll(PDO::FETCH_COLUMN);
        $sealed = 0;
        foreach ($ids as $id) {
            if (!is_int($id) && !is_string($id)) {
                continue;
            }$pdo->beginTransaction();
            try {
                if ($this->sealOne((int)$id)) {
                    ++$sealed;
                }$pdo->commit();
            } catch (\Throwable $error) {
                if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                }throw$error;
            }
        }return$sealed;
    }
    public function verifyChains(): void
    {
        $streams = $this->statement('SELECT id,head_record_sha256,next_sequence FROM trusted_archive_streams ORDER BY id ASC');
        $streams->execute();
        while (($stream = $streams->fetch(PDO::FETCH_ASSOC)) !== false) {
            if (!is_array($stream)) {
                continue;
            }$streamId = self::integer($stream['id'] ?? null);
            $nextSequence = self::integer($stream['next_sequence'] ?? null);
            $records = $this->statement('SELECT sequence_number,manifest_sha256,previous_record_sha256,record_sha256 FROM trusted_archive_records WHERE stream_id=:stream_id ORDER BY sequence_number ASC');
            $records->execute([':stream_id' => $streamId]);
            $items = [];
            while (($record = $records->fetch(PDO::FETCH_ASSOC)) !== false) {
                if (!is_array($record)) {
                    throw new \RuntimeException('Trusted archive record is malformed.');
                }$items[] = ['sequence' => self::integer($record['sequence_number'] ?? null),'manifest_sha256' => self::string($record['manifest_sha256'] ?? null),'previous_record_sha256' => is_string($record['previous_record_sha256'] ?? null) ? $record['previous_record_sha256'] : null,'record_sha256' => self::string($record['record_sha256'] ?? null)];
            }if (!$this->hasher->verifyChain($items)) {
                throw new \RuntimeException('Trusted archive hash-chain verification failed.');
            }$head = $items === [] ? null : $items[array_key_last($items)]['record_sha256'];
            if (($head === null && $stream['head_record_sha256'] !== null) || ($head !== null && (!is_string($stream['head_record_sha256'] ?? null) || !hash_equals($head, $stream['head_record_sha256'])))) {
                throw new \RuntimeException('Trusted archive stream head drifts from its records.');
            }if ($nextSequence !== count($items) + 1) {
                throw new \RuntimeException('Trusted archive sequence is discontinuous.');
            }
        }
    }
    private function sealOne(int $certificateId): bool
    {
        $certificate = $this->statement("SELECT id,public_id,workspace_id,certificate_number,certificate_type,manifest_sha256,pdf_sha256,issued_at FROM certificates WHERE id=:id AND status='ISSUED' FOR UPDATE");
        $certificate->execute([':id' => $certificateId]);
        $row = $certificate->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || !is_string($row['public_id']) || !is_string($row['manifest_sha256']) || !is_string($row['pdf_sha256'])) {
            return false;
        }$exists = $this->statement("SELECT id FROM trusted_archive_records WHERE record_kind='CERTIFICATE' AND source_public_id=:source FOR UPDATE");
        $exists->execute([':source' => $row['public_id']]);
        if ($exists->fetchColumn() !== false) {
            return false;
        }
        $workspaceId = self::integer($row['workspace_id'] ?? null);
        $certificatePublicId = self::string($row['public_id']);
        $certificateNumber = self::string($row['certificate_number'] ?? null);
        $certificateType = self::string($row['certificate_type'] ?? null);
        $certificateManifest = self::string($row['manifest_sha256']);
        $certificatePdf = self::string($row['pdf_sha256']);
        $issuedAt = self::string($row['issued_at'] ?? null);
        $stream = $this->statement("SELECT id,next_sequence,head_record_sha256 FROM trusted_archive_streams WHERE workspace_id=:workspace_id AND stream_code='CERTIFICATES' FOR UPDATE");
        $stream->execute([':workspace_id' => $workspaceId]);
        $streamRow = $stream->fetch(PDO::FETCH_ASSOC);
        $now = $this->time();
        if (!is_array($streamRow)) {
            $insert = $this->statement("INSERT INTO trusted_archive_streams (public_id,workspace_id,stream_code,next_sequence,head_record_sha256,status,version,created_at,updated_at,sealed_at) VALUES (:public_id,:workspace_id,'CERTIFICATES',1,NULL,'ACTIVE',1,:now,:now,NULL)");
            $insert->execute([':public_id' => UuidV7::generate()->toBinary(),':workspace_id' => $workspaceId,':now' => $now]);
            $streamRow = ['id' => self::integer($this->connections->connection()->lastInsertId()),'next_sequence' => 1,'head_record_sha256' => null];
        }
        $streamId = self::integer($streamRow['id'] ?? null);
        $sequence = self::integer($streamRow['next_sequence'] ?? null);
        $previous = is_string($streamRow['head_record_sha256'] ?? null) ? $streamRow['head_record_sha256'] : null;
        $manifest = json_encode(['certificate_number' => $certificateNumber,'certificate_public_id' => UuidV7::fromBinary($certificatePublicId)->toString(),'certificate_type' => $certificateType,'certificate_manifest_sha256' => bin2hex($certificateManifest),'issued_at' => $issuedAt,'pdf_sha256' => bin2hex($certificatePdf)], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $manifestHash = hash('sha256', $manifest, true);
        $recordHash = $this->hasher->recordHash($sequence, $manifestHash, $previous);
        $record = $this->statement("INSERT INTO trusted_archive_records (public_id,workspace_id,stream_id,sequence_number,record_kind,source_public_id,manifest_canonical_json,manifest_sha256,previous_record_sha256,record_sha256,status,sealed_at,supersedes_record_id,created_by_account_id,created_at) VALUES (:public_id,:workspace_id,:stream_id,:sequence,'CERTIFICATE',:source_public_id,:manifest,:manifest_sha256,:previous,:record_sha256,'SEALED',:now,NULL,NULL,:now)");
        $record->execute([':public_id' => UuidV7::generate()->toBinary(),':workspace_id' => $workspaceId,':stream_id' => $streamId,':sequence' => $sequence,':source_public_id' => $certificatePublicId,':manifest' => $manifest,':manifest_sha256' => $manifestHash,':previous' => $previous,':record_sha256' => $recordHash,':now' => $now]);
        $recordId = self::integer($this->connections->connection()->lastInsertId());
        $head = $this->statement('UPDATE trusted_archive_streams SET next_sequence=:next_sequence,head_record_sha256=:head,version=version+1,updated_at=:now WHERE id=:id AND next_sequence=:sequence');
        $head->execute([':next_sequence' => $sequence + 1,':head' => $recordHash,':now' => $now,':id' => $streamId,':sequence' => $sequence]);
        if ($head->rowCount() !== 1) {
            throw new \DomainException('Trusted archive stream changed concurrently.');
        }foreach (['APPENDED','SEALED'] as $eventType) {
            $event = $this->statement("INSERT INTO trusted_archive_events (public_id,workspace_id,archive_record_id,event_type,actor_type,actor_account_id,safe_reason_code,occurred_at,created_at) VALUES (:public_id,:workspace_id,:record_id,:event_type,'SYSTEM',NULL,NULL,:now,:now)");
            $event->execute([':public_id' => UuidV7::generate()->toBinary(),':workspace_id' => $workspaceId,':record_id' => $recordId,':event_type' => $eventType,':now' => $now]);
        }return true;
    }
    private function statement(string $sql): PDOStatement
    {
        $statement = $this->connections->connection()->prepare($sql);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Trusted archive statement could not be prepared.');
        }return$statement;
    }
    private static function integer(mixed $value): int
    {
        if (!is_int($value) && !is_string($value)) {
            throw new \UnexpectedValueException('Trusted archive integer column is malformed.');
        }return(int)$value;
    }
    private static function string(mixed $value): string
    {
        if (!is_string($value)) {
            throw new \UnexpectedValueException('Trusted archive string column is malformed.');
        }return$value;
    }
    private function time(): string
    {
        return(new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
    }
}
