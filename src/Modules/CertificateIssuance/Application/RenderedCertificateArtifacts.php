<?php

declare(strict_types=1);

namespace Qmdb\Modules\CertificateIssuance\Application;

/** Bytes generated locally before immutable storage promotion. */
final readonly class RenderedCertificateArtifacts
{
    public function __construct(public string $pdf, public string $qrSvg)
    {
        if ($pdf === '' || $qrSvg === '') {
            throw new \InvalidArgumentException('Certificate artifact output must not be empty.');
        }
    }

    public function pdfSha256(): string
    {
        return hash('sha256', $this->pdf, true);
    }
}
