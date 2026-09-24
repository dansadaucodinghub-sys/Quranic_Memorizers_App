<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\CertificateIssuance;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\CertificateIssuance\Domain\CertificateLifecycle;
use Qmdb\Modules\CertificateIssuance\Infrastructure\Artifact\LocalCertificateArtifactStore;

final class CertificateLifecycleAndArtifactStoreTest extends TestCase
{
    public function testLifecyclePermitsOnlyForwardCertificateTransitions(): void
    {
        $lifecycle = new CertificateLifecycle();
        $lifecycle->assertTransition('PREPARED', 'ISSUED');
        $lifecycle->assertTransition('ISSUED', 'REVOKED');
        $this->expectException(\DomainException::class);
        $lifecycle->assertTransition('REVOKED', 'ISSUED');
    }

    public function testArtifactStoreIsImmutableAndDoesNotExposeAPathFromKey(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'qmdb-p8-artifact-' . bin2hex(random_bytes(8));
        try {
            $store = new LocalCertificateArtifactStore($root);
            $store->put('certificates/a/manifest.json', '{"safe":true}', 'application/json');
            self::assertSame('{"safe":true}', $store->get('certificates/a/manifest.json'));
            $this->expectException(\DomainException::class);
            $store->put('certificates/a/manifest.json', '{"safe":false}', 'application/json');
        } finally {
            $path = $root . DIRECTORY_SEPARATOR . 'certificates' . DIRECTORY_SEPARATOR . 'a' . DIRECTORY_SEPARATOR . 'manifest.json';
            if (is_file($path)) {
                unlink($path);
            }
            $directory = dirname($path);
            if (is_dir($directory)) {
                rmdir($directory);
            }
            $directory = dirname($directory);
            if (is_dir($directory)) {
                rmdir($directory);
            }
            if (is_dir($root)) {
                rmdir($root);
            }
        }
    }

    public function testArtifactStoreRejectsTraversalAndUnsupportedMedia(): void
    {
        $store = new LocalCertificateArtifactStore(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'qmdb-p8-artifact-no-write');
        $this->expectException(\InvalidArgumentException::class);
        $store->put('../public/leak.txt', 'x', 'text/plain');
    }

    public function testArtifactStoreRejectsTraversalOnReadToo(): void
    {
        $store = new LocalCertificateArtifactStore(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'qmdb-p8-artifact-no-read');
        $this->expectException(\InvalidArgumentException::class);
        $store->get('../private/certificate.pdf');
    }
}
