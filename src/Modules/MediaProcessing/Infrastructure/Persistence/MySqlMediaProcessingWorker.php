<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaProcessing\Infrastructure\Persistence;

use Qmdb\Modules\MediaProcessing\Application\MediaProcessingWorker;
use Qmdb\Modules\MediaProcessing\Application\MediaProcessor;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Qmdb\Modules\MediaIngestion\Application\MediaBlobStore;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

/** Lease-based FFmpeg worker. It creates immutable private variants and never approves media. */
final readonly class MySqlMediaProcessingWorker implements MediaProcessingWorker
{
    public function __construct(private DatabaseConnectionProvider $connections, private MediaBlobStore $storage, private MediaProcessor $processor, private \Qmdb\Modules\MediaProcessing\Application\MediaProbe $probe)
    {
    }
    /** @return array{claimed:bool,outcome:string} */ public function processOne(): array
    {
        $job = $this->claim();
        if ($job === null) {
            return['claimed' => false,'outcome' => 'NO_WORK'];
        }try {
            $profile = $job['kind'] === 'AUDIO' ? ['profile' => 'AUDIO_NORMALIZED','input_mime' => '','output_mime' => 'audio/mpeg'] : ['profile' => 'VIDEO_NORMALIZED','input_mime' => '','output_mime' => 'video/mp4'];
            $original = $this->storage->get($job['storage_key']);
            if (strlen($original) !== $job['byte_size'] || !hash_equals($job['sha256'], hash('sha256', $original, true))) {
                throw new \RuntimeException('Media source integrity failed.');
            }
            $metadata = $this->probe->inspect($job['storage_key']);
            if ($metadata['duration_ms'] < 1 || $metadata['duration_ms'] > 3_600_000 || $metadata['codec'] === 'UNKNOWN' || ($job['kind'] === 'VIDEO' && ($metadata['width'] === null || $metadata['height'] === null || $metadata['width'] > 3840 || $metadata['height'] > 2160))) {
                throw new \RuntimeException('Media technical metadata is outside the bounded processing profile.');
            }
            $contents = $this->processor->process($original, $profile);
            if ($contents === '' || strlen($contents) >= 16_777_216) {
                throw new \RuntimeException('Processed media exceeds its bounded size.');
            }
            return $this->finish($job, $contents, $profile['output_mime']);
        } catch (\Throwable) {
            return $this->retry($job, 'PROCESSOR_ERROR');
        }
    }
    /** @return array{id:int,workspace_id:int,asset_id:int,asset_version:int,asset_public_id:string,storage_key:string,kind:string,attempt:int,max_attempts:int,sha256:string,byte_size:int}|null */
    private function claim(): ?array
    {
        $pdo = $this->connections->connection();
        $pdo->beginTransaction();
        try {
            $pdo->exec("UPDATE media_processing_jobs SET status=CASE WHEN attempt>=max_attempts THEN 'DEAD' ELSE 'QUEUED' END,lease_expires_at=NULL,available_at=UTC_TIMESTAMP(6),updated_at=UTC_TIMESTAMP(6),version=version+1 WHERE job_type IN ('PROCESS_AUDIO','PROCESS_VIDEO') AND status='LEASED' AND lease_expires_at<UTC_TIMESTAMP(6) ORDER BY id LIMIT 100");
            $s = $pdo->prepare("SELECT j.id,j.workspace_id,j.asset_id,j.attempt,j.max_attempts,a.version,a.public_id,a.original_storage_object_key,a.media_kind,a.original_sha256,a.byte_size FROM media_processing_jobs j INNER JOIN media_assets a ON a.id=j.asset_id AND a.workspace_id=j.workspace_id WHERE j.job_type IN ('PROCESS_AUDIO','PROCESS_VIDEO') AND j.status='QUEUED' AND j.attempt<j.max_attempts AND j.available_at<=UTC_TIMESTAMP(6) AND a.status='PROCESSING' AND NOT EXISTS (SELECT 1 FROM media_holds h WHERE h.asset_id=a.id AND h.released_at IS NULL) ORDER BY j.id LIMIT 1 FOR UPDATE SKIP LOCKED");
            if (!$s instanceof \PDOStatement) {
                throw new \RuntimeException('Media process claim could not be prepared.');
            }$s->execute();
            $r = $s->fetch(PDO::FETCH_ASSOC);
            if (!is_array($r)) {
                $pdo->commit();
                return null;
            }
            foreach (['id','workspace_id','asset_id','attempt','max_attempts','version','byte_size'] as $key) {
                if (!is_numeric($r[$key] ?? null)) {
                    throw new \UnexpectedValueException('Invalid media job numeric fields.');
                }
            }
            foreach (['public_id','original_storage_object_key','media_kind','original_sha256'] as $key) {
                if (!is_string($r[$key] ?? null)) {
                    throw new \UnexpectedValueException('Invalid media job identity fields.');
                }
            }
            $u = $pdo->prepare("UPDATE media_processing_jobs SET status='LEASED',attempt=attempt+1,lease_expires_at=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 10 MINUTE),updated_at=UTC_TIMESTAMP(6),version=version+1 WHERE id=:id AND status='QUEUED'");
            if (!$u instanceof \PDOStatement) {
                throw new \RuntimeException('Media process lease could not be prepared.');
            }$u->execute([':id' => (int)$r['id']]);
            if ($u->rowCount() !== 1) {
                throw new \DomainException('Media process lease changed concurrently.');
            }$pdo->commit();
            return['id' => (int)$r['id'],'workspace_id' => (int)$r['workspace_id'],'asset_id' => (int)$r['asset_id'],'asset_version' => (int)$r['version'],'asset_public_id' => UuidV7::fromBinary($r['public_id'])->toString(),'storage_key' => $r['original_storage_object_key'],'kind' => $r['media_kind'],'attempt' => (int)$r['attempt'] + 1,'max_attempts' => (int)$r['max_attempts'],'sha256' => $r['original_sha256'],'byte_size' => (int)$r['byte_size']];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }throw$e;
        }
    }
    /**
     * @param array{id:int,workspace_id:int,asset_id:int,asset_version:int,asset_public_id:string,storage_key:string,kind:string,attempt:int,max_attempts:int,sha256:string,byte_size:int} $job
     * @return array{claimed:bool,outcome:string}
     */
    private function finish(array $job, string $contents, string $mime): array
    {
        $pdo = $this->connections->connection();
        $pdo->beginTransaction();
        try {
            $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
            $a = $pdo->prepare("UPDATE media_assets SET status='PENDING_MODERATION',updated_at=:now,version=version+1 WHERE id=:asset AND workspace_id=:workspace AND status='PROCESSING' AND version=:version");
            if (!$a instanceof \PDOStatement) {
                throw new \RuntimeException('Media processing transition could not be prepared.');
            }$a->execute([':now' => $now,':asset' => $job['asset_id'],':workspace' => $job['workspace_id'],':version' => $job['asset_version']]);
            if ($a->rowCount() !== 1) {
                throw new \DomainException('Media processing transition changed concurrently.');
            }
            $lease = $pdo->prepare("SELECT id FROM media_processing_jobs WHERE id=:id AND attempt=:attempt AND status='LEASED' AND lease_expires_at>UTC_TIMESTAMP(6) FOR UPDATE");
            $lease->execute(['id' => $job['id'],'attempt' => $job['attempt']]);
            if ($lease->fetchColumn() === false) {
                throw new \DomainException('Media processing lease expired.');
            }
            $key = 'variants/' . $job['asset_public_id'] . '/' . hash('sha256', $contents) . ($job['kind'] === 'AUDIO' ? '.mp3' : '.mp4');
            $this->storage->putImmutable($key, $contents);
            $v = $pdo->prepare("INSERT INTO media_variants (public_id,workspace_id,asset_id,variant_code,storage_provider_code,storage_object_key,mime_type,byte_size,sha256,status,is_public_safe,created_at) VALUES (:public_id,:workspace,:asset,'NORMALIZED_V1','LOCAL_PRIVATE',:key,:mime,:bytes,:sha256,'READY',0,:now)");
            if (!$v instanceof \PDOStatement) {
                throw new \RuntimeException('Media variant statement could not be prepared.');
            }$v->execute([':public_id' => UuidV7::generate()->toBinary(),':workspace' => $job['workspace_id'],':asset' => $job['asset_id'],':key' => $key,':mime' => $mime,':bytes' => strlen($contents),':sha256' => hash('sha256', $contents, true),':now' => $now]);
            $event = $pdo->prepare("INSERT INTO media_events (workspace_id,asset_id,event_code,actor_account_id,created_at) VALUES (:workspace,:asset,'media.processing.completed',NULL,:now)");
            if (!$event instanceof \PDOStatement) {
                throw new \RuntimeException('Media processing event could not be prepared.');
            }$event->execute([':workspace' => $job['workspace_id'],':asset' => $job['asset_id'],':now' => $now]);
            $j = $pdo->prepare("UPDATE media_processing_jobs SET status='SUCCEEDED',lease_expires_at=NULL,completed_at=:completed,updated_at=:updated,version=version+1 WHERE id=:id AND status='LEASED' AND attempt=:attempt");
            if (!$j instanceof \PDOStatement) {
                throw new \RuntimeException('Media processing completion could not be prepared.');
            }$j->execute([':completed' => $now,':updated' => $now,':id' => $job['id'],':attempt' => $job['attempt']]);
            if ($j->rowCount() !== 1) {
                throw new \DomainException('Media processing completion changed concurrently.');
            }$pdo->commit();
            return['claimed' => true,'outcome' => 'PROCESSED'];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }throw$e;
        }
    }
    /**
     * @param array{id:int,workspace_id:int,asset_id:int,asset_version:int,asset_public_id:string,storage_key:string,kind:string,attempt:int,max_attempts:int,sha256:string,byte_size:int} $job
     * @return array{claimed:bool,outcome:string}
     */
    private function retry(array $job, string $code): array
    {
        $pdo = $this->connections->connection();
        $s = $pdo->prepare("UPDATE media_processing_jobs SET status=:status,lease_expires_at=NULL,available_at=CASE WHEN :retry_status='QUEUED' THEN DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 60 SECOND) ELSE available_at END,completed_at=CASE WHEN :terminal_status='DEAD' THEN UTC_TIMESTAMP(6) ELSE NULL END,safe_failure_code=:code,updated_at=UTC_TIMESTAMP(6),version=version+1 WHERE id=:id AND status='LEASED' AND attempt=:attempt");
        if (!$s instanceof \PDOStatement) {
            throw new \RuntimeException('Media processing retry could not be prepared.');
        }$status = $job['attempt'] >= $job['max_attempts'] ? 'DEAD' : 'QUEUED';
        $s->execute([':status' => $status,':retry_status' => $status,':terminal_status' => $status,':code' => $code,':id' => $job['id'],':attempt' => $job['attempt']]);
        if ($s->rowCount() !== 1) {
            throw new \DomainException('Media processing retry changed concurrently.');
        }return['claimed' => true,'outcome' => $status];
    }
}
