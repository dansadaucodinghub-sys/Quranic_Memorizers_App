<?php

declare(strict_types=1);

namespace Qmdb\Modules\CertificateIssuance\Application;

/** Immutable storage boundary; keys must never be public filesystem paths. */
interface CertificateArtifactStore
{
    public function put(string $objectKey, string $contents, string $mediaType): void;
    public function get(string $objectKey): string;
}
