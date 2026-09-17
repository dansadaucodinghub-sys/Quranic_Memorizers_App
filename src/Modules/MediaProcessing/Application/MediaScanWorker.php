<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaProcessing\Application;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Qmdb\Modules\MediaIngestion\Application\MediaBlobStore;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

/** Lease-based scan worker: unavailable scanners keep an asset quarantined. */
final readonly class MediaScanWorker
{
    public function __construct(private DatabaseConnectionProvider $connections, private MediaBlobStore $storage, private MediaScanner $scanner) {}

    /** @return array{claimed:bool,outcome:string} */
    public function processOne(): array
    {
        $job=$this->claim(); if($job===null)return ['claimed'=>false,'outcome'=>'NO_WORK'];
        try { $result=$this->scanner->scan($this->storage->get($job['storage_key'])); }
        catch(\Throwable) { $result=['clean'=>false,'engine'=>'SCANNER','safe_code'=>'SCANNER_ERROR']; }
        if(!is_bool($result['clean']??null)||!is_string($result['engine']??null)||!is_string($result['safe_code']??null))throw new \UnexpectedValueException('Media scanner result is malformed.');
        return $this->finish($job,$result);
    }

    /** @return array{id:int,workspace_id:int,asset_id:int,asset_version:int,status:string,storage_key:string,kind:string,attempt:int,max_attempts:int}|null */
    private function claim(): ?array
    {
        $pdo=$this->connections->connection(); $pdo->beginTransaction();
        try {
            $statement=$pdo->prepare("SELECT j.id,j.workspace_id,j.asset_id,j.attempt,j.max_attempts,a.version,a.status,a.original_storage_object_key,a.media_kind FROM media_processing_jobs j INNER JOIN media_assets a ON a.id=j.asset_id AND a.workspace_id=j.workspace_id WHERE j.job_type='SCAN' AND j.status='QUEUED' AND j.available_at<=UTC_TIMESTAMP(6) AND a.status='QUARANTINED' ORDER BY j.id LIMIT 1 FOR UPDATE SKIP LOCKED");
            if(!$statement instanceof \PDOStatement)throw new \RuntimeException('Media scan claim could not be prepared.'); $statement->execute(); $row=$statement->fetch(PDO::FETCH_ASSOC);
            if(!is_array($row)){ $pdo->commit(); return null; }
            foreach(['id','workspace_id','asset_id','attempt','max_attempts','version'] as $key)if(!is_numeric($row[$key]??null))throw new \UnexpectedValueException('Media scan job is malformed.');
            foreach(['status','original_storage_object_key','media_kind'] as $key)if(!is_string($row[$key]??null))throw new \UnexpectedValueException('Media scan job is malformed.');
            $update=$pdo->prepare("UPDATE media_processing_jobs SET status='LEASED',attempt=attempt+1,lease_expires_at=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 5 MINUTE),updated_at=UTC_TIMESTAMP(6),version=version+1 WHERE id=:id AND status='QUEUED'"); if(!$update instanceof \PDOStatement)throw new \RuntimeException('Media scan lease could not be prepared.'); $update->execute([':id'=>(int)$row['id']]); if($update->rowCount()!==1)throw new \RuntimeException('Media scan lease changed concurrently.');
            $pdo->commit(); return ['id'=>(int)$row['id'],'workspace_id'=>(int)$row['workspace_id'],'asset_id'=>(int)$row['asset_id'],'asset_version'=>(int)$row['version'],'status'=>$row['status'],'storage_key'=>$row['original_storage_object_key'],'kind'=>$row['media_kind'],'attempt'=>(int)$row['attempt']+1,'max_attempts'=>(int)$row['max_attempts']];
        } catch(\Throwable $error) { if($pdo->inTransaction())$pdo->rollBack(); throw $error; }
    }

    /** @param array{id:int,workspace_id:int,asset_id:int,asset_version:int,status:string,storage_key:string,kind:string,attempt:int,max_attempts:int} $job @param array{clean:bool,engine:string,safe_code:string} $result @return array{claimed:bool,outcome:string} */
    private function finish(array $job,array $result): array
    {
        $pdo=$this->connections->connection(); $pdo->beginTransaction();
        try {
            $asset=$pdo->prepare('SELECT id,status,version FROM media_assets WHERE id=:asset AND workspace_id=:workspace FOR UPDATE'); if(!$asset instanceof \PDOStatement)throw new \RuntimeException('Media asset lock could not be prepared.');$asset->execute([':asset'=>$job['asset_id'],':workspace'=>$job['workspace_id']]);$row=$asset->fetch(PDO::FETCH_ASSOC);if(!is_array($row)||$row['status']!=='QUARANTINED'||(int)$row['version']!==$job['asset_version'])throw new \DomainException('Media asset changed while scanning.');
            $jobLock=$pdo->prepare("SELECT id FROM media_processing_jobs WHERE id=:id AND status='LEASED' FOR UPDATE");if(!$jobLock instanceof \PDOStatement)throw new \RuntimeException('Media scan completion lock could not be prepared.');$jobLock->execute([':id'=>$job['id']]);if($jobLock->fetchColumn()===false)throw new \DomainException('Media scan lease is unavailable.');
            $now=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
            $code=$result['clean']?'CLEAN':($result['safe_code']==='INFECTED'||$result['safe_code']==='MALWARE_TEST_SIGNATURE'?'INFECTED':'ERROR');
            if($code==='CLEAN'){$target='PROCESSING';$event='media.scan.clean';$jobStatus='SUCCEEDED';}elseif($code==='INFECTED'){$target='REJECTED';$event='media.scan.infected';$jobStatus='SUCCEEDED';}else{$target='QUARANTINED';$event='media.scan.error';$jobStatus=$job['attempt']>=$job['max_attempts']?'DEAD':'QUEUED';}
            if($code!=='ERROR'||$jobStatus==='DEAD'){$scan=$pdo->prepare('INSERT INTO media_scan_results (workspace_id,asset_id,scan_job_id,result_code,engine_code,safe_detail_code,scanned_at,created_at) VALUES (:workspace,:asset,:job,:result,:engine,:detail,:now,:now)');if(!$scan instanceof \PDOStatement)throw new \RuntimeException('Media scan evidence could not be prepared.');$scan->execute([':workspace'=>$job['workspace_id'],':asset'=>$job['asset_id'],':job'=>$job['id'],':result'=>$code,':engine'=>substr($result['engine'],0,64),':detail'=>substr($result['safe_code'],0,64),':now'=>$now]);}
            if($target!=='QUARANTINED'){$move=$pdo->prepare('UPDATE media_assets SET status=:status,updated_at=:now,version=version+1 WHERE id=:asset AND workspace_id=:workspace AND status=\'QUARANTINED\' AND version=:version');if(!$move instanceof \PDOStatement)throw new \RuntimeException('Media scan transition could not be prepared.');$move->execute([':status'=>$target,':now'=>$now,':asset'=>$job['asset_id'],':workspace'=>$job['workspace_id'],':version'=>$job['asset_version']]);if($move->rowCount()!==1)throw new \DomainException('Media scan transition changed concurrently.');}
            $eventInsert=$pdo->prepare('INSERT INTO media_events (workspace_id,asset_id,event_code,actor_account_id,created_at) VALUES (:workspace,:asset,:event,NULL,:now)');if(!$eventInsert instanceof \PDOStatement)throw new \RuntimeException('Media scan event could not be prepared.');$eventInsert->execute([':workspace'=>$job['workspace_id'],':asset'=>$job['asset_id'],':event'=>$event,':now'=>$now]);
            $finish=$pdo->prepare("UPDATE media_processing_jobs SET status=:status,lease_expires_at=NULL,available_at=CASE WHEN :status='QUEUED' THEN DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 60 SECOND) ELSE available_at END,completed_at=CASE WHEN :status IN ('SUCCEEDED','DEAD') THEN :now ELSE NULL END,safe_failure_code=:failure,updated_at=:now,version=version+1 WHERE id=:id AND status='LEASED'");if(!$finish instanceof \PDOStatement)throw new \RuntimeException('Media scan completion could not be prepared.');$finish->execute([':status'=>$jobStatus,':now'=>$now,':failure'=>$code==='CLEAN'?null:$result['safe_code'],':id'=>$job['id']]);if($finish->rowCount()!==1)throw new \DomainException('Media scan completion changed concurrently.');
            if($code==='CLEAN'){$enqueue=$pdo->prepare("INSERT IGNORE INTO media_processing_jobs (public_id,workspace_id,asset_id,job_type,status,attempt,max_attempts,available_at,version,created_at,updated_at) VALUES (:public_id,:workspace,:asset,:type,'QUEUED',0,3,:now,1,:now,:now)");if(!$enqueue instanceof \PDOStatement)throw new \RuntimeException('Media processing job could not be prepared.');$enqueue->execute([':public_id'=>UuidV7::generate()->toBinary(),':workspace'=>$job['workspace_id'],':asset'=>$job['asset_id'],':type'=>$job['kind']==='VIDEO'?'PROCESS_VIDEO':'PROCESS_AUDIO',':now'=>$now]);}
            $pdo->commit();return ['claimed'=>true,'outcome'=>$code];
        }catch(\Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
    }
}
