<?php

declare(strict_types=1);

namespace Qmdb\Modules\CertificateIssuance\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOStatement;
use Qmdb\Modules\CertificateIssuance\Application\CertificateIssuanceRepository;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Schema\State\PdoResultReader;

/** All issuance writes occur inside the application transaction and lock their source rows. */
/**
 * @phpstan-import-type SourceRecord from CertificateIssuanceRepository
 * @phpstan-import-type TemplateRecord from CertificateIssuanceRepository
 * @phpstan-import-type SigningKeyRecord from CertificateIssuanceRepository
 * @phpstan-import-type CertificateRecord from CertificateIssuanceRepository
 */
final readonly class MySqlCertificateIssuanceRepository implements CertificateIssuanceRepository
{
    public function __construct(private DatabaseConnectionProvider $connections)
    {
    }

    /** @return SourceRecord|null */
    public function lockFinalizedResultRow(int $workspaceId, UuidV7 $publicationId, UuidV7 $rowId): ?array
    {
        $statement = $this->statement("SELECT p.id AS publication_id,p.public_id AS publication_public_id,p.status AS publication_status,p.version AS publication_version,r.id AS result_run_id,r.result_checksum_sha256,row_record.id AS result_row_id,row_record.public_id AS result_row_public_id,row_record.rank_position,row_record.public_label,participant.competitor_person_id,person.public_id AS person_public_id,person_name.display_name FROM competition_result_publications p INNER JOIN competition_result_runs r ON r.workspace_id=p.workspace_id AND r.id=p.result_run_id INNER JOIN competition_result_rows row_record ON row_record.workspace_id=p.workspace_id AND row_record.result_run_id=r.id INNER JOIN competition_round_participants participant ON participant.workspace_id=row_record.workspace_id AND participant.id=row_record.participant_id INNER JOIN people_persons person ON person.id=participant.competitor_person_id LEFT JOIN people_person_names person_name ON person_name.person_id=person.id AND person_name.name_type='PRIMARY' AND person_name.status='ACTIVE' WHERE p.workspace_id=:workspace_id AND p.public_id=:publication_id AND row_record.public_id=:row_id AND p.status='FINALIZED' FOR UPDATE");
        $statement->execute([':workspace_id' => $workspaceId, ':publication_id' => $publicationId->toBinary(), ':row_id' => $rowId->toBinary()]);
        $row = self::row($statement->fetch(PDO::FETCH_ASSOC));
        if ($row === null) {
            return null;
        }
        $display = is_string($row['display_name'] ?? null) && $row['display_name'] !== '' ? $row['display_name'] : (is_string($row['public_label'] ?? null) ? $row['public_label'] : 'Recognized participant');
        return ['publication_id' => PdoResultReader::integer($row, 'publication_id'), 'publication_public_id' => UuidV7::fromBinary(PdoResultReader::string($row, 'publication_public_id'))->toString(), 'result_run_id' => PdoResultReader::integer($row, 'result_run_id'), 'result_package_sha256' => self::hash($row, 'result_checksum_sha256'), 'result_row_id' => PdoResultReader::integer($row, 'result_row_id'), 'result_row_public_id' => UuidV7::fromBinary(PdoResultReader::string($row, 'result_row_public_id'))->toString(), 'rank_position' => PdoResultReader::integer($row, 'rank_position'), 'person_id' => PdoResultReader::integer($row, 'competitor_person_id'), 'person_public_id' => UuidV7::fromBinary(PdoResultReader::string($row, 'person_public_id'))->toString(), 'display_name' => $display];
    }

    /** @return TemplateRecord|null */
    public function lockActiveTemplate(int $workspaceId, UuidV7 $templateId): ?array
    {
        $statement = $this->statement("SELECT id,public_id,template_code,template_version,configuration_sha256 FROM certificate_templates WHERE workspace_id=:workspace_id AND public_id=:public_id AND status='ACTIVE' FOR UPDATE");
        $statement->execute([':workspace_id' => $workspaceId, ':public_id' => $templateId->toBinary()]);
        $row = self::row($statement->fetch(PDO::FETCH_ASSOC));
        return $row === null ? null : ['id' => PdoResultReader::integer($row, 'id'), 'public_id' => UuidV7::fromBinary(PdoResultReader::string($row, 'public_id'))->toString(), 'template_code' => PdoResultReader::string($row, 'template_code'), 'template_version' => PdoResultReader::integer($row, 'template_version'), 'configuration_sha256' => self::hash($row, 'configuration_sha256')];
    }

    /** @return SigningKeyRecord|null */
    public function lockActiveSigningKey(): ?array
    {
        $statement = $this->statement("SELECT id,key_code,provider_code,provider_key_reference,public_key,status FROM certificate_signing_keys WHERE status='ACTIVE' AND valid_from<=UTC_TIMESTAMP(6) AND (valid_until IS NULL OR valid_until>UTC_TIMESTAMP(6)) FOR UPDATE");
        $statement->execute();
        $row = self::row($statement->fetch(PDO::FETCH_ASSOC));
        return $row === null ? null : ['id' => PdoResultReader::integer($row, 'id'), 'key_code' => PdoResultReader::string($row, 'key_code'), 'provider_code' => PdoResultReader::string($row, 'provider_code'), 'provider_key_reference' => PdoResultReader::string($row, 'provider_key_reference'), 'public_key' => PdoResultReader::string($row, 'public_key'), 'status' => PdoResultReader::string($row, 'status')];
    }

    /** @return CertificateRecord|null */
    public function lockCertificate(int $workspaceId, UuidV7 $certificateId): ?array
    {
        $statement = $this->statement('SELECT c.id,c.public_id,c.workspace_id,c.certificate_number,c.verification_code_hash,c.verification_code_fingerprint,c.certificate_type,c.status,c.version,c.display_name,c.result_publication_id,c.result_run_id,c.result_row_id,c.template_id,c.signing_key_id,c.result_package_sha256,k.key_code,k.provider_code,k.provider_key_reference,k.public_key,k.status AS key_status,p.public_id AS result_publication_public_id,row_record.public_id AS result_row_public_id,t.template_code,t.template_version FROM certificates c INNER JOIN certificate_signing_keys k ON k.id=c.signing_key_id INNER JOIN competition_result_publications p ON p.workspace_id=c.workspace_id AND p.id=c.result_publication_id INNER JOIN competition_result_rows row_record ON row_record.id=c.result_row_id INNER JOIN certificate_templates t ON t.workspace_id=c.workspace_id AND t.id=c.template_id WHERE c.workspace_id=:workspace_id AND c.public_id=:public_id FOR UPDATE');
        $statement->execute([':workspace_id' => $workspaceId, ':public_id' => $certificateId->toBinary()]);
        return $this->certificate(self::row($statement->fetch(PDO::FETCH_ASSOC)));
    }

    public function completed(UuidV7 $submissionId, string $fingerprint): ?array
    {
        $statement = $this->statement('SELECT request_fingerprint,certificate_id,result_status,version_after FROM certificate_operations WHERE submission_id=:submission_id FOR UPDATE');
        $statement->execute([':submission_id' => $submissionId->toBinary()]);
        $row = self::row($statement->fetch(PDO::FETCH_ASSOC));
        if ($row === null) {
            return null;
        }
        if (!hash_equals(PdoResultReader::string($row, 'request_fingerprint'), $fingerprint)) {
            throw new \DomainException('Certificate submission conflicts with a prior request.');
        }
        $certificate = $this->statement('SELECT public_id FROM certificates WHERE id=:id');
        $certificate->execute([':id' => PdoResultReader::integer($row, 'certificate_id')]);
        $public = $certificate->fetchColumn();
        if (!is_string($public)) {
            throw new \RuntimeException('Certificate replay target is unavailable.');
        }
        return ['certificate_id' => UuidV7::fromBinary($public)->toString(), 'status' => PdoResultReader::string($row, 'result_status'), 'version' => PdoResultReader::integer($row, 'version_after')];
    }

    /** @return CertificateRecord */
    public function insertPrepared(array $prepared, DateTimeImmutable $now): array
    {
        $id = UuidV7::generate();
        $time = self::time($now);
        $statement = $this->statement("INSERT INTO certificates (public_id,workspace_id,certificate_number,verification_code_hash,verification_code_fingerprint,certificate_type,person_id,account_id,result_publication_id,result_run_id,result_row_id,template_id,signing_key_id,status,display_name,display_name_source_code,result_package_sha256,prepared_by_account_id,created_at,updated_at,prepared_at) VALUES (:public_id,:workspace_id,:certificate_number,:verification_hash,:verification_fingerprint,:certificate_type,:person_id,NULL,:publication_id,:result_run_id,:result_row_id,:template_id,:signing_key_id,'PREPARED',:display_name,'PERSON_PRIMARY_NAME',:result_hash,:actor,:now,:now,:now)");
        $statement->execute([':public_id' => $id->toBinary(), ':workspace_id' => $prepared['workspace_id'], ':certificate_number' => $prepared['certificate_number'], ':verification_hash' => $prepared['verification_hash'], ':verification_fingerprint' => $prepared['verification_fingerprint'], ':certificate_type' => $prepared['certificate_type'], ':person_id' => $prepared['person_id'], ':publication_id' => $prepared['publication_id'], ':result_run_id' => $prepared['result_run_id'], ':result_row_id' => $prepared['result_row_id'], ':template_id' => $prepared['template_id'], ':signing_key_id' => $prepared['signing_key_id'], ':display_name' => $prepared['display_name'], ':result_hash' => $prepared['result_package_sha256'], ':actor' => $prepared['actor_account_id'], ':now' => $time]);
        return $this->lockCertificate((int) $prepared['workspace_id'], $id) ?? throw new \RuntimeException('Prepared certificate could not be locked.');
    }

    /** @param CertificateRecord $certificate */
    public function issue(array $certificate, string $manifest, string $manifestHash, string $signature, string $pdfHash, int $actorAccountId, DateTimeImmutable $now): bool
    {
        $statement = $this->statement("UPDATE certificates SET status='ISSUED',manifest_canonical_json=:manifest,manifest_sha256=:manifest_hash,detached_signature=:signature,signature_algorithm='ED25519',pdf_sha256=:pdf_hash,issued_by_account_id=:actor,issued_at=:now,updated_at=:now,version=version+1 WHERE id=:id AND workspace_id=:workspace_id AND status='PREPARED' AND version=:version");
        $statement->execute([':manifest' => $manifest, ':manifest_hash' => $manifestHash, ':signature' => $signature, ':pdf_hash' => $pdfHash, ':actor' => $actorAccountId, ':now' => self::time($now), ':id' => $certificate['id'], ':workspace_id' => $certificate['workspace_id'], ':version' => $certificate['version']]);
        return $statement->rowCount() === 1;
    }

    /** @param CertificateRecord $certificate */
    public function transition(array $certificate, string $target, int $actorAccountId, ?string $reason, DateTimeImmutable $now): bool
    {
        $extra = match ($target) {
            'REVOKED' => 'revoked_by_account_id=:actor,revoked_at=:now,revocation_reason_code=:reason', 'ARCHIVED' => 'archived_at=:now', default => throw new \InvalidArgumentException('Certificate target is invalid.')
        };
        $statement = $this->statement("UPDATE certificates SET status=:status,{$extra},updated_at=:now,version=version+1 WHERE id=:id AND workspace_id=:workspace_id AND status=:previous AND version=:version");
        $statement->execute([':status' => $target, ':actor' => $actorAccountId, ':reason' => $reason, ':now' => self::time($now), ':id' => $certificate['id'], ':workspace_id' => $certificate['workspace_id'], ':previous' => $certificate['status'], ':version' => $certificate['version']]);
        return $statement->rowCount() === 1;
    }

    /** @param CertificateRecord $certificate */
    public function appendEvent(array $certificate, string $event, string $target, int $actorAccountId, ?string $reason, DateTimeImmutable $now): void
    {
        $statement = $this->statement("INSERT INTO certificate_events (public_id,workspace_id,certificate_id,event_type,from_status,to_status,actor_type,actor_account_id,safe_reason_code,correlation_id,occurred_at,created_at) VALUES (:public_id,:workspace_id,:certificate_id,:event,:from_status,:to_status,'ACCOUNT',:actor,:reason,:correlation,:now,:now)");
        $statement->execute([':public_id' => UuidV7::generate()->toBinary(), ':workspace_id' => $certificate['workspace_id'], ':certificate_id' => $certificate['id'], ':event' => $event, ':from_status' => $certificate['status'], ':to_status' => $target, ':actor' => $actorAccountId, ':reason' => $reason, ':correlation' => UuidV7::generate()->toBinary(), ':now' => self::time($now)]);
    }

    /** @param CertificateRecord $certificate */
    public function appendArtifacts(array $certificate, string $pdfKey, string $pdf, string $manifestKey, string $manifest, DateTimeImmutable $now): void
    {
        foreach ([['PDF',$pdfKey,'application/pdf',$pdf], ['MANIFEST',$manifestKey,'application/json',$manifest]] as [$type,$key,$media,$bytes]) {
            $statement = $this->statement('INSERT INTO certificate_artifacts (public_id,workspace_id,certificate_id,artifact_type,storage_provider_code,storage_object_key,media_type,byte_size,sha256,renderer_code,renderer_version,created_at) VALUES (:public_id,:workspace_id,:certificate_id,:type,\'LOCAL_PRIVATE\',:object_key,:media_type,:byte_size,:sha256,\'dompdf\',\'3.1\',:now)');
            $statement->execute([':public_id' => UuidV7::generate()->toBinary(), ':workspace_id' => $certificate['workspace_id'], ':certificate_id' => $certificate['id'], ':type' => $type, ':object_key' => $key, ':media_type' => $media, ':byte_size' => strlen($bytes), ':sha256' => hash('sha256', $bytes, true), ':now' => self::time($now)]);
        }
    }

    /** @param CertificateRecord $certificate */
    public function record(UuidV7 $submissionId, string $fingerprint, string $operation, array $certificate, string $status, int $versionAfter, DateTimeImmutable $now): void
    {
        $statement = $this->statement('INSERT INTO certificate_operations (public_id,submission_id,workspace_id,operation_code,request_fingerprint,certificate_id,result_status,version_after,occurred_at) VALUES (:public_id,:submission_id,:workspace_id,:operation,:fingerprint,:certificate_id,:status,:version,:now)');
        $statement->execute([':public_id' => UuidV7::generate()->toBinary(), ':submission_id' => $submissionId->toBinary(), ':workspace_id' => $certificate['workspace_id'], ':operation' => $operation, ':fingerprint' => $fingerprint, ':certificate_id' => $certificate['id'], ':status' => $status, ':version' => $versionAfter, ':now' => self::time($now)]);
    }

    private function statement(string $sql): PDOStatement
    {
        $statement = $this->connections->connection()->prepare($sql);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Certificate statement could not be prepared.');
        } return $statement;
    }
    /** @return array<string,mixed>|null */
    private static function row(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }
        foreach ($value as $key => $_) {
            if (!is_string($key)) {
                throw new \UnexpectedValueException('Certificate database row is malformed.');
            }
        }
        return $value;
    }
    /**
     * @param array<string,mixed>|null $row
     * @return CertificateRecord|null
     */
    private function certificate(?array $row): ?array
    {
        if ($row === null) {
            return null;
        } return ['id' => PdoResultReader::integer($row, 'id'),'public_id' => UuidV7::fromBinary(PdoResultReader::string($row, 'public_id'))->toString(),'workspace_id' => PdoResultReader::integer($row, 'workspace_id'),'certificate_number' => PdoResultReader::string($row, 'certificate_number'),'verification_code_hash' => PdoResultReader::string($row, 'verification_code_hash'),'verification_code_fingerprint' => PdoResultReader::string($row, 'verification_code_fingerprint'),'certificate_type' => PdoResultReader::string($row, 'certificate_type'),'status' => PdoResultReader::string($row, 'status'),'version' => PdoResultReader::integer($row, 'version'),'display_name' => PdoResultReader::string($row, 'display_name'),'result_publication_id' => PdoResultReader::integer($row, 'result_publication_id'),'result_run_id' => PdoResultReader::integer($row, 'result_run_id'),'result_row_id' => PdoResultReader::integer($row, 'result_row_id'),'template_id' => PdoResultReader::integer($row, 'template_id'),'signing_key_id' => PdoResultReader::integer($row, 'signing_key_id'),'result_package_sha256' => self::hash($row, 'result_package_sha256'),'key_code' => PdoResultReader::string($row, 'key_code'),'provider_code' => PdoResultReader::string($row, 'provider_code'),'provider_key_reference' => PdoResultReader::string($row, 'provider_key_reference'),'public_key' => PdoResultReader::string($row, 'public_key'),'key_status' => PdoResultReader::string($row, 'key_status'),'result_publication_public_id' => UuidV7::fromBinary(PdoResultReader::string($row, 'result_publication_public_id'))->toString(),'result_row_public_id' => UuidV7::fromBinary(PdoResultReader::string($row, 'result_row_public_id'))->toString(),'template_code' => PdoResultReader::string($row, 'template_code'),'template_version' => PdoResultReader::integer($row, 'template_version')];
    }
    /** @param array<string,mixed> $row */ private static function hash(array $row, string $field): string
    {
        $value = PdoResultReader::string($row, $field);
        if (strlen($value) !== 32) {
            throw new \UnexpectedValueException('Certificate checksum is invalid.');
        } return $value;
    }
    private static function time(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }
}
