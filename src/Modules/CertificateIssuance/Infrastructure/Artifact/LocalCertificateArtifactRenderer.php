<?php

declare(strict_types=1);

namespace Qmdb\Modules\CertificateIssuance\Infrastructure\Artifact;

use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Qmdb\Modules\CertificateIssuance\Application\CertificateArtifactRenderer;
use Qmdb\Modules\CertificateIssuance\Application\RenderedCertificateArtifacts;

/**
 * Bounded local renderer.  The signed manifest binds the resulting PDF hash,
 * so presentation metadata can never alter the authoritative statement.
 */
final class LocalCertificateArtifactRenderer implements CertificateArtifactRenderer
{
    private const MAX_PDF_BYTES = 5_000_000;

    public function render(string $verificationUrl, array $display): RenderedCertificateArtifacts
    {
        if (filter_var($verificationUrl, FILTER_VALIDATE_URL) === false || !str_starts_with($verificationUrl, 'https://')) {
            throw new \InvalidArgumentException('Certificate verification URL must be an approved HTTPS URL.');
        }
        $qr = (new SvgWriter())->write(new QrCode(
            data: $verificationUrl,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 180,
            margin: 8,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        ))->getString();
        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setDefaultFont('DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->setPaper('A4', 'landscape');
        $pdf->loadHtml($this->html($qr, $display), 'UTF-8');
        $pdf->render();
        $bytes = $pdf->output();
        if ($bytes === '' || strlen($bytes) > self::MAX_PDF_BYTES || !str_starts_with($bytes, '%PDF-')) {
            throw new \RuntimeException('Certificate PDF renderer produced an invalid artifact.');
        }

        return new RenderedCertificateArtifacts($bytes, $qr);
    }

    /** @param array<string, string|int|null> $display */
    private function html(string $qr, array $display): string
    {
        $name = $this->escape((string) ($display['display_name'] ?? ''));
        $number = $this->escape((string) ($display['certificate_number'] ?? ''));
        $type = $this->escape((string) ($display['certificate_type'] ?? ''));
        $date = $this->escape((string) ($display['issued_at'] ?? ''));
        $qrEncoded = base64_encode($qr);

        return '<!doctype html><html lang="en"><meta charset="utf-8"><style>'
            . '@page{margin:25mm}body{font-family:DejaVu Sans,sans-serif;color:#1e293b;text-align:center}.frame{border:4px solid #334155;padding:18mm}.label{font-size:14pt;text-transform:uppercase;letter-spacing:3px}.name{font-size:29pt;margin:20mm 0 6mm}.meta{font-size:11pt}.qr{width:34mm;height:34mm;margin-top:12mm}</style>'
            . '<body><main class="frame"><h1>Certificate of Recognition</h1><p class="label">' . $type . '</p><p>This certificate is awarded to</p><p class="name">' . $name . '</p><p class="meta">Certificate number: ' . $number . '</p><p class="meta">Issued: ' . $date . '</p><img class="qr" alt="Certificate verification QR code" src="data:image/svg+xml;base64,' . $qrEncoded . '"></main></body></html>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
