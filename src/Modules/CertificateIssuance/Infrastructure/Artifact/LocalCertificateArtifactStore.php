<?php

declare(strict_types=1);

namespace Qmdb\Modules\CertificateIssuance\Infrastructure\Artifact;

use Qmdb\Modules\CertificateIssuance\Application\CertificateArtifactStore;

/** Test/development store outside public/. Production must configure a private mounted volume. */
final readonly class LocalCertificateArtifactStore implements CertificateArtifactStore
{
    public function __construct(private string $root)
    {
        if ($root === '' || str_contains($root, "\0")) {
            throw new \InvalidArgumentException('Certificate artifact root is invalid.');
        }
    }

    public function put(string $objectKey, string $contents, string $mediaType): void
    {
        if (!$this->validObjectKey($objectKey) || !in_array($mediaType, ['application/pdf', 'application/json'], true) || $contents === '') {
            throw new \InvalidArgumentException('Certificate artifact is invalid.');
        }
        $path = $this->path($objectKey);
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Certificate artifact directory cannot be created.');
        }
        if (file_exists($path)) {
            $existing = file_get_contents($path);
            if (!is_string($existing) || !hash_equals(hash('sha256', $existing, true), hash('sha256', $contents, true))) {
                throw new \DomainException('Certificate artifact key is immutable.');
            }
            return;
        }
        $temporary = $path . '.tmp-' . bin2hex(random_bytes(8));
        if (file_put_contents($temporary, $contents, LOCK_EX) !== strlen($contents) || !rename($temporary, $path)) {
            @unlink($temporary);
            throw new \RuntimeException('Certificate artifact cannot be stored.');
        }
        @chmod($path, 0600);
    }

    public function get(string $objectKey): string
    {
        if (!$this->validObjectKey($objectKey)) {
            throw new \InvalidArgumentException('Certificate artifact key is invalid.');
        }
        $value = file_get_contents($this->path($objectKey));
        if (!is_string($value)) {
            throw new \RuntimeException('Certificate artifact is unavailable.');
        }
        return $value;
    }

    private function path(string $objectKey): string { return rtrim($this->root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $objectKey); }
    private function validObjectKey(string $objectKey): bool { return preg_match('~\A[a-z0-9][a-z0-9._/-]{2,300}\z~', $objectKey) === 1 && !str_contains($objectKey, '..'); }
}
