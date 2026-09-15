<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\CertificateIssuance;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\CertificateIssuance\Domain\CertificateManifestCanonicalizer;
use Qmdb\Modules\CertificateIssuance\Domain\CertificateSigningKey;
use Qmdb\Modules\CertificateIssuance\Domain\CertificateTemplateConfigurationValidator;
use Qmdb\Modules\CertificateIssuance\Domain\CertificateVerificationCode;
use Qmdb\Modules\CertificateIssuance\Infrastructure\Security\Ed25519CertificateSignatureVerifier;

final class CertificateCryptographicBoundaryTest extends TestCase
{
    public function testCanonicalManifestIsStableAndSignsExactBytes(): void
    {
        $canonicalizer = new CertificateManifestCanonicalizer();
        $left = $canonicalizer->canonicalize(['certificate_number' => 'QMDB-2026-W-000001', 'schema_version' => 1, 'display_name' => 'Amina']);
        $right = $canonicalizer->canonicalize(['display_name' => 'Amina', 'schema_version' => 1, 'certificate_number' => 'QMDB-2026-W-000001']);
        self::assertSame($left, $right);

        $keypair = sodium_crypto_sign_keypair();
        $key = new CertificateSigningKey('P8-TEST-KEY', 'TEST_ONLY', 'ephemeral', sodium_crypto_sign_publickey($keypair), 'ACTIVE');
        $signature = sodium_crypto_sign_detached($left, sodium_crypto_sign_secretkey($keypair));
        $verifier = new Ed25519CertificateSignatureVerifier();
        self::assertTrue($verifier->verify($key, $left, $signature));
        self::assertFalse($verifier->verify($key, $left . "\n", $signature));
    }

    public function testManifestRejectsAmbiguousOrInvalidContent(): void
    {
        $canonicalizer = new CertificateManifestCanonicalizer();
        $this->expectException(\InvalidArgumentException::class);
        $canonicalizer->canonicalize(['value' => 1.2]);
    }

    public function testVerificationCodesAreOpaqueAndOnlyProtectedLookupMaterialIsDerived(): void
    {
        $first = CertificateVerificationCode::generate();
        $second = CertificateVerificationCode::generate();
        self::assertMatchesRegularExpression('/\A[A-Za-z0-9_-]{43}\z/', $first->value());
        self::assertNotSame($first->value(), $second->value());
        self::assertSame(32, strlen($first->hash()));
        self::assertSame(16, strlen($first->fingerprint()));
    }

    public function testTemplateConfigurationRejectsExecutableAndRemoteContent(): void
    {
        $validator = new CertificateTemplateConfigurationValidator();
        $validator->validate(['border_style_code' => 'INSTITUTIONAL', 'result_fields' => ['rank_position']]);
        $this->expectException(\InvalidArgumentException::class);
        $validator->validate(['approved_logo_asset' => 'https://untrusted.example/logo.svg']);
    }
}
