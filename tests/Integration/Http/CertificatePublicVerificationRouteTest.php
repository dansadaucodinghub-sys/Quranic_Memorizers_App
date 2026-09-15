<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\Http;

use PHPUnit\Framework\TestCase;
use Nyholm\Psr7\Factory\Psr17Factory;
use Qmdb\Modules\CertificateIssuance\Application\CertificateSignatureVerifier;
use Qmdb\Modules\CertificateIssuance\Application\CertificateArtifactStore;
use Qmdb\Modules\CertificateIssuance\Domain\CertificateSigningKey;
use Qmdb\Modules\CertificateVerification\Application\PublicCertificateVerificationReader;
use Qmdb\Modules\CertificateVerification\Interface\Http\PublicCertificateVerificationController;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprint;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitAttempt;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitDecision;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Shared\Http\Routing\RouteAttributes;
use Qmdb\Tests\Support\Http\HttpTestFactory;
use Qmdb\Tests\Support\Http\ProductionHttpRuntimeFactory;

final class CertificatePublicVerificationRouteTest extends TestCase
{
    public function testUnknownOpaqueCertificateCodeIsNonDisclosing(): void
    {
        $runtime = ProductionHttpRuntimeFactory::create();
        $response = $runtime->handle(HttpTestFactory::request('GET', '/verify/certificates/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'));
        self::assertSame(404, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
        self::assertStringContainsString('public', $response->getHeaderLine('Cache-Control'));
    }

    public function testMalformedCertificateCodeIsNonDisclosing(): void
    {
        $runtime = ProductionHttpRuntimeFactory::create();
        $response = $runtime->handle(HttpTestFactory::request('GET', '/verify/certificates/not-a-valid-code'));
        self::assertSame(404, $response->getStatusCode());
    }

    public function testValidCertificateHonoursExactEtagWithoutRecheckingARepresentation(): void
    {
        $manifest = '{"certificate_number":"QMDB-001","schema_version":1}';
        $hash = hash('sha256', $manifest, true);
        $controller = new PublicCertificateVerificationController(
            new class($manifest, $hash) implements PublicCertificateVerificationReader {
                public function __construct(private string $manifest, private string $hash) {}
                public function byVerificationCode(string $verificationCode): ?array { if ($verificationCode === '') return null; return ['certificate_number' => 'QMDB-001', 'certificate_type' => 'MERIT', 'status' => 'ISSUED', 'issued_at' => null, 'manifest' => $this->manifest, 'manifest_sha256' => $this->hash, 'signature' => str_repeat('s', 64), 'public_key' => str_repeat('p', 32), 'key_status' => 'ACTIVE', 'pdf_sha256' => str_repeat('p', 32)]; }
                public function pdfByVerificationCode(string $verificationCode): ?array { return null; }
            },
            new class implements CertificateSignatureVerifier { public function verify(CertificateSigningKey $key, string $canonicalManifest, string $signature): bool { return true; } },
            new class implements CertificateArtifactStore { public function put(string $objectKey, string $contents, string $mediaType): void {} public function get(string $objectKey): string { throw new \RuntimeException('Not used by this test.'); } },
            new class implements IdentityRateLimiter { public function consume(array $attempts, \DateTimeImmutable $now): IdentityRateLimitDecision { return IdentityRateLimitDecision::allowed(); } public function reset(array $attempts): void {} },
            new class implements IdentityFingerprintGenerator { public function generate(string $domain, string $value): IdentityFingerprint { return new IdentityFingerprint(hash('sha256', $domain . $value, true)); } },
            new Psr17Factory(),
        );
        $response = $controller->handle(HttpTestFactory::request('GET', '/verify/certificates/test')->withAttribute(RouteAttributes::PARAMETERS, ['verificationCode' => 'test'])->withHeader('If-None-Match', '"' . bin2hex($hash) . '"'));
        self::assertSame(304, $response->getStatusCode());
        self::assertSame('"' . bin2hex($hash) . '"', $response->getHeaderLine('ETag'));
        self::assertSame('', (string) $response->getBody());
    }

    public function testPublicPdfIsServedOnlyWhenItsStoredChecksumMatchesTheSignedCertificateRecord(): void
    {
        $manifest = '{"certificate_number":"QMDB-002","schema_version":1}';
        $manifestHash = hash('sha256', $manifest, true);
        $pdf = "%PDF-1.4\nP8 test artifact\n";
        $pdfHash = hash('sha256', $pdf, true);
        $controller = new PublicCertificateVerificationController(
            new class($manifest, $manifestHash, $pdfHash) implements PublicCertificateVerificationReader {
                public function __construct(private string $manifest, private string $manifestHash, private string $pdfHash) {}
                public function byVerificationCode(string $verificationCode): ?array { if ($verificationCode === '') return null; return ['certificate_number' => 'QMDB-002', 'certificate_type' => 'MERIT', 'status' => 'ISSUED', 'issued_at' => null, 'manifest' => $this->manifest, 'manifest_sha256' => $this->manifestHash, 'signature' => str_repeat('s', 64), 'public_key' => str_repeat('p', 32), 'key_status' => 'ACTIVE', 'pdf_sha256' => $this->pdfHash]; }
                public function pdfByVerificationCode(string $verificationCode): ?array { return $verificationCode === '' ? null : ['object_key' => 'certificates/p8/certificate.pdf', 'pdf_sha256' => $this->pdfHash, 'certificate_number' => 'QMDB-002']; }
            },
            new class implements CertificateSignatureVerifier { public function verify(CertificateSigningKey $key, string $canonicalManifest, string $signature): bool { return true; } },
            new class($pdf) implements CertificateArtifactStore { public function __construct(private string $pdf) {} public function put(string $objectKey, string $contents, string $mediaType): void {} public function get(string $objectKey): string { return $this->pdf; } },
            new class implements IdentityRateLimiter { public function consume(array $attempts, \DateTimeImmutable $now): IdentityRateLimitDecision { return IdentityRateLimitDecision::allowed(); } public function reset(array $attempts): void {} },
            new class implements IdentityFingerprintGenerator { public function generate(string $domain, string $value): IdentityFingerprint { return new IdentityFingerprint(hash('sha256', $domain . $value, true)); } },
            new Psr17Factory(),
        );
        $response = $controller->handle(HttpTestFactory::request('GET', '/verify/certificates/test/pdf')->withAttribute(RouteAttributes::PARAMETERS, ['verificationCode' => 'test']));
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/pdf', $response->getHeaderLine('Content-Type'));
        self::assertSame('"' . bin2hex($pdfHash) . '"', $response->getHeaderLine('ETag'));
        self::assertSame($pdf, (string) $response->getBody());
    }
}
