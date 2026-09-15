<?php

declare(strict_types=1);

namespace Qmdb\Modules\CertificateVerification\Infrastructure\Persistence;

use PDO;
use PDOStatement;
use Qmdb\Modules\CertificateVerification\Application\PublicCertificateVerificationReader;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;

/** Narrow public read model: the opaque code resolves no private identity data. */
final readonly class MySqlPublicCertificateVerificationReader implements PublicCertificateVerificationReader
{
    public function __construct(private DatabaseConnectionProvider $connections)
    {
    }

    public function byVerificationCode(string $verificationCode): ?array
    {
        if (preg_match('/\A[A-Za-z0-9_-]{43}\z/', $verificationCode) !== 1) {
            return null;
        }
        $statement = $this->connections->connection()->prepare("SELECT c.certificate_number,c.certificate_type,c.status,c.issued_at,c.manifest_canonical_json,c.manifest_sha256,c.detached_signature,c.pdf_sha256,k.public_key,k.status AS key_status FROM certificates c INNER JOIN certificate_signing_keys k ON k.id=c.signing_key_id WHERE c.verification_code_hash=:hash AND c.status IN ('ISSUED','REVOKED','SUPERSEDED','ARCHIVED') LIMIT 1");
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Public certificate verification query could not be prepared.');
        }
        $statement->execute([':hash' => hash('sha256', $verificationCode, true)]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || !is_string($row['certificate_number'] ?? null) || !is_string($row['certificate_type'] ?? null) || !is_string($row['status'] ?? null) || !is_string($row['manifest_canonical_json'] ?? null) || !is_string($row['manifest_sha256'] ?? null) || !is_string($row['detached_signature'] ?? null) || !is_string($row['public_key'] ?? null) || !is_string($row['key_status'] ?? null) || !is_string($row['pdf_sha256'] ?? null)) {
            return null;
        }

        return [
            'certificate_number' => $row['certificate_number'], 'certificate_type' => $row['certificate_type'],
            'status' => $row['status'], 'issued_at' => is_string($row['issued_at'] ?? null) ? $row['issued_at'] : null,
            'manifest' => $row['manifest_canonical_json'], 'manifest_sha256' => $row['manifest_sha256'],
            'signature' => $row['detached_signature'], 'public_key' => $row['public_key'], 'key_status' => $row['key_status'], 'pdf_sha256' => $row['pdf_sha256'],
        ];
    }

    public function pdfByVerificationCode(string $verificationCode): ?array
    {
        if (preg_match('/\A[A-Za-z0-9_-]{43}\z/', $verificationCode) !== 1) {
            return null;
        }
        $statement = $this->connections->connection()->prepare("SELECT a.storage_object_key,c.pdf_sha256,c.certificate_number FROM certificates c INNER JOIN certificate_artifacts a ON a.certificate_id=c.id AND a.artifact_type='PDF' WHERE c.verification_code_hash=:hash AND c.status IN ('ISSUED','REVOKED','SUPERSEDED','ARCHIVED') LIMIT 1");
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Public certificate PDF query could not be prepared.');
        }
        $statement->execute([':hash' => hash('sha256', $verificationCode, true)]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || !is_string($row['storage_object_key'] ?? null) || !is_string($row['pdf_sha256'] ?? null) || !is_string($row['certificate_number'] ?? null)) {
            return null;
        }

        return ['object_key' => $row['storage_object_key'], 'pdf_sha256' => $row['pdf_sha256'], 'certificate_number' => $row['certificate_number']];
    }
}
