<?php

declare(strict_types=1);

namespace Qmdb\Modules\CertificateVerification\Interface\Http;

use DateTimeImmutable;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\CertificateIssuance\Application\CertificateSignatureVerifier;
use Qmdb\Modules\CertificateIssuance\Application\CertificateArtifactStore;
use Qmdb\Modules\CertificateIssuance\Domain\CertificateSigningKey;
use Qmdb\Modules\CertificateVerification\Application\PublicCertificateVerificationReader;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitAttempt;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitPolicy;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitScope;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Routing\RouteAttributes;

final readonly class PublicCertificateVerificationController implements Controller
{
    public function __construct(private PublicCertificateVerificationReader $reader, private CertificateSignatureVerifier $signatures, private CertificateArtifactStore $artifacts, private IdentityRateLimiter $rateLimits, private IdentityFingerprintGenerator $fingerprints, private Psr17Factory $responses)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $parameters = $request->getAttribute(RouteAttributes::PARAMETERS);
        $code = is_array($parameters) && is_string($parameters['verificationCode'] ?? null) ? $parameters['verificationCode'] : '';
        try {
            $peer = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
            $attempt = new IdentityRateLimitAttempt(IdentityRateLimitScope::CERTIFICATE_PUBLIC_VERIFY_PEER, $this->fingerprints->generate('certificate-public-verify-peer', is_string($peer) ? $peer : 'unknown'), new IdentityRateLimitPolicy(60, 30, 60));
            $decision = $this->rateLimits->consume([$attempt], new DateTimeImmutable('now'));
            if (!$decision->allowed) {
                return $this->responses->createResponse(429)->withHeader('Cache-Control', 'no-store')->withHeader('Retry-After', (string)$decision->retryAfterSeconds);
            }
            $certificate = $this->reader->byVerificationCode($code);
            if ($certificate === null) {
                return $this->notFound();
            }
            $manifestHash = hash('sha256', $certificate['manifest'], true);
            $signatureValid = hash_equals($certificate['manifest_sha256'], $manifestHash) && $this->signatures->verify(new CertificateSigningKey('PUBLIC', 'PUBLIC', 'PUBLIC', $certificate['public_key'], $certificate['key_status']), $certificate['manifest'], $certificate['signature']);
            $status = $signatureValid ? $certificate['status'] : 'INVALID';
            $etag = '"' . bin2hex($manifestHash) . '"';
            if (str_ends_with($request->getUri()->getPath(), '/pdf')) {
                return $this->pdf($code, $certificate, $signatureValid, $request);
            }
            if ($signatureValid && $request->getHeaderLine('If-None-Match') === $etag) {
                return $this->responses->createResponse(304)->withHeader('Cache-Control', 'public, max-age=300')->withHeader('ETag', $etag);
            }
            if (str_ends_with($request->getUri()->getPath(), '/manifest')) {
                return $this->manifest($certificate, $signatureValid, $etag);
            }
            $html = '<main><h1>Certificate verification</h1><p aria-live="polite">Status: ' . $this->escape($status) . '</p><dl><dt>Certificate number</dt><dd><bdi>' . $this->escape($certificate['certificate_number']) . '</bdi></dd><dt>Type</dt><dd>' . $this->escape($certificate['certificate_type']) . '</dd><dt>Signature</dt><dd>' . ($signatureValid ? 'Verified' : 'Invalid') . '</dd></dl></main>';
            $response = $this->responses->createResponse(200)->withHeader('Content-Type', 'text/html; charset=utf-8')->withHeader('Cache-Control', 'public, max-age=300')->withHeader('ETag', $etag);
            $response->getBody()->write($html);

            return $response;
        } catch (\Throwable) {
            return $this->notFound();
        }
    }

    /** @param array{certificate_number:string,certificate_type:string,status:string,issued_at:?string,manifest:string,manifest_sha256:string,signature:string,public_key:string,key_status:string,pdf_sha256:string} $certificate */
    private function manifest(array $certificate, bool $signatureValid, string $etag): ResponseInterface
    {
        $payload = ['manifest' => json_decode($certificate['manifest'], true, 64, JSON_THROW_ON_ERROR), 'manifest_sha256' => bin2hex($certificate['manifest_sha256']), 'signature' => base64_encode($certificate['signature']), 'signature_algorithm' => 'ED25519', 'signature_valid' => $signatureValid, 'key_status' => $certificate['key_status']];
        $response = $this->responses->createResponse(200)->withHeader('Content-Type', 'application/json; charset=utf-8')->withHeader('Cache-Control', 'public, max-age=300')->withHeader('ETag', $etag);
        $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $response;
    }

    /** @param array{certificate_number:string,certificate_type:string,status:string,issued_at:?string,manifest:string,manifest_sha256:string,signature:string,public_key:string,key_status:string,pdf_sha256:string} $certificate */
    private function pdf(string $verificationCode, array $certificate, bool $signatureValid, ServerRequestInterface $request): ResponseInterface
    {
        if (!$signatureValid) {
            return $this->notFound();
        }
        $artifact = $this->reader->pdfByVerificationCode($verificationCode);
        if ($artifact === null) {
            return $this->notFound();
        }
        $pdf = $this->artifacts->get($artifact['object_key']);
        if (!hash_equals($certificate['pdf_sha256'], hash('sha256', $pdf, true)) || !hash_equals($artifact['pdf_sha256'], $certificate['pdf_sha256'])) {
            return $this->notFound();
        }
        $etag = '"' . bin2hex($certificate['pdf_sha256']) . '"';
        if ($request->getHeaderLine('If-None-Match') === $etag) {
            return $this->responses->createResponse(304)->withHeader('Cache-Control', 'public, max-age=300')->withHeader('ETag', $etag);
        }
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '-', $artifact['certificate_number']);
        $response = $this->responses->createResponse(200)->withHeader('Content-Type', 'application/pdf')->withHeader('Content-Disposition', 'inline; filename="certificate-' . $filename . '.pdf"')->withHeader('Cache-Control', 'public, max-age=300')->withHeader('ETag', $etag)->withHeader('X-Content-Type-Options', 'nosniff');
        $response->getBody()->write($pdf);
        return $response;
    }

    private function notFound(): ResponseInterface { return $this->responses->createResponse(404)->withHeader('Cache-Control', 'public, max-age=60'); }
    private function escape(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
