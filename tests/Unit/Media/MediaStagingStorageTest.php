<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Media;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\MediaIngestion\Infrastructure\Storage\LocalPrivateMediaBlobStore;

final class MediaStagingStorageTest extends TestCase
{
    private string $root;
    private LocalPrivateMediaBlobStore $store;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/qmdb-staging-test-' . bin2hex(random_bytes(12));
        self::assertTrue(mkdir($this->root, 0700));
        $this->store = new LocalPrivateMediaBlobStore($this->root);
    }
    protected function tearDown(): void
    {
        foreach (['staging', 'variants'] as $prefix) {
            foreach (glob($this->root . '/' . $prefix . '/*') ?: [] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
            if (is_dir($this->root . '/' . $prefix)) {
                rmdir($this->root . '/' . $prefix);
            }
        }
        rmdir($this->root);
    }
    public function testImmutablePromotionAndSameByteReplayLeaveNoPartialFile(): void
    {
        $this->store->putImmutable('staging/sample.bin', 'synthetic');
        $this->store->putImmutable('staging/sample.bin', 'synthetic');
        self::assertSame('synthetic', $this->store->get('staging/sample.bin'));
        self::assertSame([], glob($this->root . '/staging/.writing-*'));
        $this->expectException(\DomainException::class);
        $this->store->putImmutable('staging/sample.bin', 'different');
    }
    public function testChecksummedStagingRemovalIsRetrySafe(): void
    {
        $this->store->putImmutable('staging/sample.bin', 'synthetic');
        self::assertTrue($this->store->removeStaging('staging/sample.bin', hash('sha256', 'synthetic', true)));
        self::assertFalse($this->store->removeStaging('staging/sample.bin', hash('sha256', 'synthetic', true)));
    }
    public function testChangedBytesAreRetainedForInvestigation(): void
    {
        $this->store->putImmutable('staging/sample.bin', 'synthetic');
        try {
            $this->store->removeStaging('staging/sample.bin', hash('sha256', 'different', true));
            self::fail('Mismatched evidence cannot be removed.');
        } catch (\DomainException) {
            self::assertSame('synthetic', $this->store->get('staging/sample.bin'));
        }
    }
    public function testCleanupCannotDeleteAnApprovedVariant(): void
    {
        $this->store->putImmutable('variants/sample.mp3', 'synthetic');
        $this->expectException(\InvalidArgumentException::class);
        $this->store->removeStaging('variants/sample.mp3', hash('sha256', 'synthetic', true));
    }
}
