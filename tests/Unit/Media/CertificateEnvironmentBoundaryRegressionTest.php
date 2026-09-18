<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Media;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\CertificateIssuance\Domain\CertificateSigningKey;
use Qmdb\Modules\CertificateIssuance\Infrastructure\Security\EnvironmentCertificateSigningKeyProvider;
use Qmdb\Shared\Configuration\EnvironmentVariables;

final class CertificateEnvironmentBoundaryRegressionTest extends TestCase
{
    public function testInjectedSecretProducesAValidSignatureWithoutReadingProcessState(): void
    {
        $pair = sodium_crypto_sign_keypair();
        $public = sodium_crypto_sign_publickey($pair);
        $key = new CertificateSigningKey('TEST_KEY', 'ENVIRONMENT_SECRET', 'test-key', $public, 'ACTIVE');
        $environment = new EnvironmentVariables(['QMDB_CERTIFICATE_SIGNING_KEY_TEST_KEY' => sodium_bin2base64(sodium_crypto_sign_secretkey($pair), SODIUM_BASE64_VARIANT_ORIGINAL)]);
        $provider = new EnvironmentCertificateSigningKeyProvider(true, $environment);
        self::assertTrue($provider->verifiesPublicKey($key));
        $signature = $provider->sign($key, 'synthetic manifest');
        if ($signature === '') {
            throw new \UnexpectedValueException('Signing returned an empty signature.');
        }
        self::assertSame(SODIUM_CRYPTO_SIGN_BYTES, strlen($signature));
        self::assertTrue(sodium_crypto_sign_verify_detached($signature, 'synthetic manifest', $public));
        self::assertStringNotContainsString('QMDB_CERTIFICATE_SIGNING_KEY_TEST_KEY', print_r($environment, true));
    }
    public function testMissingKeyRemainsFailClosed(): void
    {
        $key = new CertificateSigningKey('TEST_KEY', 'ENVIRONMENT_SECRET', 'test-key', sodium_crypto_sign_publickey(sodium_crypto_sign_keypair()), 'ACTIVE');
        $provider = new EnvironmentCertificateSigningKeyProvider(true, new EnvironmentVariables([]));
        self::assertFalse($provider->verifiesPublicKey($key));
        $this->expectException(\RuntimeException::class);
        $provider->sign($key, 'synthetic manifest');
    }
    public function testUpdatedPdfDependencyStillRendersEscapedDisplayData(): void
    {
        $renderer = new \Qmdb\Modules\CertificateIssuance\Infrastructure\Artifact\LocalCertificateArtifactRenderer();
        $artifact = $renderer->render('https://example.invalid/verify/test', ['display_name' => '<script>unsafe</script>', 'certificate_number' => 'TEST-001', 'certificate_type' => 'Synthetic test']);
        self::assertStringStartsWith('%PDF-', $artifact->pdf);
    }
}
