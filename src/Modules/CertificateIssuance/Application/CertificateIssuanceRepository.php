<?php

declare(strict_types=1);

namespace Qmdb\Modules\CertificateIssuance\Application;

use DateTimeImmutable;
use Qmdb\Shared\Identifier\UuidV7;

/**
 * @phpstan-type SourceRecord array{publication_id:int,publication_public_id:string,result_run_id:int,result_package_sha256:string,result_row_id:int,result_row_public_id:string,rank_position:int,person_id:int,person_public_id:string,display_name:string}
 * @phpstan-type TemplateRecord array{id:int,public_id:string,template_code:string,template_version:int,configuration_sha256:string}
 * @phpstan-type SigningKeyRecord array{id:int,key_code:string,provider_code:string,provider_key_reference:string,public_key:string,status:string}
 * @phpstan-type CertificateRecord array{id:int,public_id:string,workspace_id:int,certificate_number:string,verification_code_hash:string,verification_code_fingerprint:string,certificate_type:string,status:string,version:int,display_name:string,result_publication_id:int,result_run_id:int,result_row_id:int,template_id:int,signing_key_id:int,result_package_sha256:string,key_code:string,provider_code:string,provider_key_reference:string,public_key:string,key_status:string,result_publication_public_id:string,result_row_public_id:string,template_code:string,template_version:int}
 */
interface CertificateIssuanceRepository
{
    /** @return SourceRecord|null */
    public function lockFinalizedResultRow(int $workspaceId, UuidV7 $publicationId, UuidV7 $rowId): ?array;
    /** @return TemplateRecord|null */
    public function lockActiveTemplate(int $workspaceId, UuidV7 $templateId): ?array;
    /** @return SigningKeyRecord|null */
    public function lockActiveSigningKey(): ?array;
    /** @return CertificateRecord|null */
    public function lockCertificate(int $workspaceId, UuidV7 $certificateId): ?array;
    /** @return array{certificate_id:string,status:string,version:int}|null */
    public function completed(UuidV7 $submissionId, string $fingerprint): ?array;
    /**
     * @param array{workspace_id:int,certificate_number:string,verification_hash:string,verification_fingerprint:string,certificate_type:string,person_id:int,publication_id:int,result_run_id:int,result_row_id:int,template_id:int,signing_key_id:int,display_name:string,result_package_sha256:string,actor_account_id:int} $prepared
     * @return CertificateRecord
     */
    public function insertPrepared(array $prepared, DateTimeImmutable $now): array;
    /** @param CertificateRecord $certificate */
    public function issue(array $certificate, string $manifest, string $manifestHash, string $signature, string $pdfHash, int $actorAccountId, DateTimeImmutable $now): bool;
    /** @param CertificateRecord $certificate */
    public function transition(array $certificate, string $target, int $actorAccountId, ?string $reason, DateTimeImmutable $now): bool;
    /** @param CertificateRecord $certificate */
    public function appendEvent(array $certificate, string $event, string $target, int $actorAccountId, ?string $reason, DateTimeImmutable $now): void;
    /** @param CertificateRecord $certificate */
    public function appendArtifacts(array $certificate, string $pdfKey, string $pdf, string $manifestKey, string $manifest, DateTimeImmutable $now): void;
    /** @param CertificateRecord $certificate */
    public function record(UuidV7 $submissionId, string $fingerprint, string $operation, array $certificate, string $status, int $versionAfter, DateTimeImmutable $now): void;
}
