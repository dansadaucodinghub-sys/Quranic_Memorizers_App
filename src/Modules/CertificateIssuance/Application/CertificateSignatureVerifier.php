<?php

declare(strict_types=1);

namespace Qmdb\Modules\CertificateIssuance\Application;

use Qmdb\Modules\CertificateIssuance\Domain\CertificateSigningKey;

interface CertificateSignatureVerifier
{
    public function verify(CertificateSigningKey $key, string $canonicalManifest, string $signature): bool;
}
