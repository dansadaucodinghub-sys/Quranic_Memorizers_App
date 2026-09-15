<?php

declare(strict_types=1);

namespace Qmdb\Modules\RecordPassport\Infrastructure\Persistence;

use PDO;
use PDOStatement;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

/** Bounded system projection of issued certificates into a Person-owned passport. */
final readonly class RecordPassportProjector
{
    public function __construct(private DatabaseConnectionProvider $connections) {}

    public function projectIssuedCertificates(int $limit = 100): int
    {
        if ($limit < 1 || $limit > 500) throw new \InvalidArgumentException('Passport projection limit is invalid.');
        $pdo=$this->connections->connection();
        $statement=$this->statement("SELECT c.id FROM certificates c WHERE c.status='ISSUED' AND NOT EXISTS (SELECT 1 FROM record_passport_entries e WHERE e.source_kind='CERTIFICATE' AND e.certificate_id=c.id) ORDER BY c.id ASC LIMIT {$limit}"); $statement->execute(); $ids=$statement->fetchAll(PDO::FETCH_COLUMN); $projected=0;
        foreach($ids as $id){if(!is_int($id)&&!is_string($id))continue;$pdo->beginTransaction();try{if($this->projectOne((int)$id))++$projected;$pdo->commit();}catch(\Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}}
        return $projected;
    }

    private function projectOne(int $certificateId): bool
    {
        $pdo=$this->connections->connection();$certificate=$this->statement("SELECT id,public_id,workspace_id,person_id,certificate_number,certificate_type,issued_at,manifest_sha256 FROM certificates WHERE id=:id AND status='ISSUED' FOR UPDATE");$certificate->execute([':id'=>$certificateId]);$row=$certificate->fetch(PDO::FETCH_ASSOC);if(!is_array($row)||!is_string($row['public_id'])||!is_string($row['manifest_sha256']))return false;
        $exists=$this->statement("SELECT id FROM record_passport_entries WHERE source_kind='CERTIFICATE' AND certificate_id=:certificate_id FOR UPDATE");$exists->execute([':certificate_id'=>$certificateId]);if($exists->fetchColumn()!==false)return false;
        $personId=self::integer($row['person_id']??null);$workspaceId=self::integer($row['workspace_id']??null);$certificatePublicId=self::string($row['public_id']);$certificateNumber=self::string($row['certificate_number']??null);$certificateType=self::string($row['certificate_type']??null);$issuedAt=self::string($row['issued_at']??null);$manifestChecksum=self::string($row['manifest_sha256']);
        $passport=$this->statement('SELECT id FROM record_passports WHERE person_id=:person_id AND status=\'ACTIVE\' FOR UPDATE');$passport->execute([':person_id'=>$personId]);$passportId=$passport->fetchColumn();$now=$this->time();
        if(!is_int($passportId)&&!is_string($passportId)){$insert=$this->statement("INSERT INTO record_passports (public_id,person_id,status,version,created_at,updated_at,archived_at) VALUES (:public_id,:person_id,'ACTIVE',1,:now,:now,NULL)");$insert->execute([':public_id'=>UuidV7::generate()->toBinary(),':person_id'=>$personId,':now'=>$now]);$passportId=self::integer($pdo->lastInsertId());$event=$this->statement("INSERT INTO record_passport_events (public_id,passport_id,event_type,actor_type,actor_account_id,safe_reason_code,correlation_id,occurred_at,created_at) VALUES (:public_id,:passport_id,'CREATED','SYSTEM',NULL,NULL,:correlation,:now,:now)");$event->execute([':public_id'=>UuidV7::generate()->toBinary(),':passport_id'=>$passportId,':correlation'=>UuidV7::generate()->toBinary(),':now'=>$now]);}
        $passportIdentifier=self::integer($passportId);$manifest=json_encode(['certificate_number'=>$certificateNumber,'certificate_public_id'=>UuidV7::fromBinary($certificatePublicId)->toString(),'certificate_type'=>$certificateType,'issued_at'=>$issuedAt,'manifest_sha256'=>bin2hex($manifestChecksum)],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $entry=$this->statement("INSERT INTO record_passport_entries (public_id,passport_id,source_workspace_id,source_kind,source_public_id,certificate_id,result_publication_id,legacy_record_id,entry_manifest_canonical_json,entry_sha256,occurred_at,created_at) VALUES (:public_id,:passport_id,:workspace_id,'CERTIFICATE',:source_public_id,:certificate_id,NULL,NULL,:manifest,:sha256,:now,:now)");$entry->execute([':public_id'=>UuidV7::generate()->toBinary(),':passport_id'=>$passportIdentifier,':workspace_id'=>$workspaceId,':source_public_id'=>$certificatePublicId,':certificate_id'=>$certificateId,':manifest'=>$manifest,':sha256'=>hash('sha256',$manifest,true),':now'=>$now]);
        $event=$this->statement("INSERT INTO record_passport_events (public_id,passport_id,event_type,actor_type,actor_account_id,safe_reason_code,correlation_id,occurred_at,created_at) VALUES (:public_id,:passport_id,'ENTRY_ADDED','SYSTEM',NULL,'CERTIFICATE_ISSUED',:correlation,:now,:now)");$event->execute([':public_id'=>UuidV7::generate()->toBinary(),':passport_id'=>$passportIdentifier,':correlation'=>UuidV7::generate()->toBinary(),':now'=>$now]);return true;
    }
    private function statement(string $sql):PDOStatement{$statement=$this->connections->connection()->prepare($sql);if(!$statement instanceof PDOStatement)throw new \RuntimeException('Passport projection statement could not be prepared.');return $statement;}
    private static function integer(mixed $value):int{if(!is_int($value)&&!is_string($value))throw new \UnexpectedValueException('Passport integer column is malformed.');return(int)$value;}
    private static function string(mixed $value):string{if(!is_string($value))throw new \UnexpectedValueException('Passport string column is malformed.');return$value;}
    private function time():string{return (new \DateTimeImmutable('now',new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');}
}
