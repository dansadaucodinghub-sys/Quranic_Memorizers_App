<?php

declare(strict_types=1);

namespace Qmdb\Modules\CertificateIssuance\Application;

use Qmdb\Modules\CertificateIssuance\Domain\CertificateSigningKey;

/**
 * An HSM/KMS/environment adapter signs inside its own trust boundary.
 * It intentionally has no method that exposes private key bytes.
 */
interface CertificateSigningKeyProvider
{
    public function providerCode(): string;

    public function sign(CertificateSigningKey $key, string $canonicalManifest): string;

    public function verifiesPublicKey(CertificateSigningKey $key): bool;
}
