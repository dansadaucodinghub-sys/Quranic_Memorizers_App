<?php

declare(strict_types=1);

namespace Qmdb\Modules\CertificateVerification\Application;

interface PublicCertificateVerificationReader
{
    /** @return array{certificate_number:string,certificate_type:string,status:string,issued_at:?string,manifest:string,manifest_sha256:string,signature:string,public_key:string,key_status:string,pdf_sha256:string}|null */
    public function byVerificationCode(string $verificationCode): ?array;

    /** @return array{object_key:string,pdf_sha256:string,certificate_number:string}|null */
    public function pdfByVerificationCode(string $verificationCode): ?array;
}
