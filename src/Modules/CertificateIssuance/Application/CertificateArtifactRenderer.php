<?php

declare(strict_types=1);

namespace Qmdb\Modules\CertificateIssuance\Application;

interface CertificateArtifactRenderer
{
    /** @param array<string, string|int|null> $display */
    public function render(string $verificationUrl, array $display): RenderedCertificateArtifacts;
}
