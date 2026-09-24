<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Community;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\Community\Application\CommunityPublicClipReader;
use Qmdb\Modules\Community\Application\CommunityPublicClipService;
use Qmdb\Modules\MediaCatalog\Application\MediaEvidenceRepository;
use Qmdb\Modules\MediaIngestion\Application\MediaBlobStore;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Identifier\UuidV7;

final class CommunityPublicClipServiceTest extends TestCase
{
    public function testDetailExposesOnlyApprovedPublicFields(): void
    {
        $clipId = UuidV7::generate();
        $reader = $this->createStub(CommunityPublicClipReader::class);
        $reader->method('find')->willReturn($this->record($clipId));
        $service = new CommunityPublicClipService(
            $reader,
            $this->createStub(MediaEvidenceRepository::class),
            $this->createStub(MediaBlobStore::class),
            $this->transactions()
        );
        $detail = $service->detail($clipId);
        self::assertNotNull($detail);
        self::assertSame('/clips/' . $clipId->toString() . '/media', $detail['media_url']);
        self::assertArrayNotHasKey('asset_id', $detail);
        self::assertArrayNotHasKey('workspace_id', $detail);
        self::assertArrayNotHasKey('storage_key', $detail);
    }

    public function testMediaRechecksP9AndNeverReturnsStorageKey(): void
    {
        $clipId = UuidV7::generate();
        $record = $this->record($clipId);
        $reader = $this->createStub(CommunityPublicClipReader::class);
        $reader->method('find')->willReturn($record);
        $media = $this->createMock(MediaEvidenceRepository::class);
        $media->expects(self::once())->method('findDeliverable')
            ->with(9, $record['asset_id'])
            ->willReturn(['storage_key' => 'private/variant.mp3', 'mime_type' => 'audio/mpeg',
                'byte_size' => 4, 'sha256' => hash('sha256', 'test', true)]);
        $storage = $this->createMock(MediaBlobStore::class);
        $storage->expects(self::once())->method('get')->with('private/variant.mp3')->willReturn('test');
        $service = new CommunityPublicClipService($reader, $media, $storage, $this->transactions());
        $result = $service->media($clipId);
        self::assertNotNull($result);
        self::assertSame('test', $result['contents']);
        self::assertArrayNotHasKey('storage_key', $result);
    }

    public function testRevokedP9MediaCannotBeDelivered(): void
    {
        $clipId = UuidV7::generate();
        $reader = $this->createStub(CommunityPublicClipReader::class);
        $reader->method('find')->willReturn($this->record($clipId));
        $media = $this->createStub(MediaEvidenceRepository::class);
        $media->method('findDeliverable')->willReturn(null);
        $storage = $this->createMock(MediaBlobStore::class);
        $storage->expects(self::never())->method('get');
        self::assertNull((new CommunityPublicClipService(
            $reader,
            $media,
            $storage,
            $this->transactions()
        ))->media($clipId));
    }

    public function testCorruptP9VariantFailsBeforeReturningBytes(): void
    {
        $clipId = UuidV7::generate();
        $reader = $this->createStub(CommunityPublicClipReader::class);
        $reader->method('find')->willReturn($this->record($clipId));
        $media = $this->createStub(MediaEvidenceRepository::class);
        $media->method('findDeliverable')->willReturn(['storage_key' => 'private/variant.mp3',
            'mime_type' => 'audio/mpeg', 'byte_size' => 4,
            'sha256' => hash('sha256', 'test', true)]);
        $storage = $this->createStub(MediaBlobStore::class);
        $storage->method('get')->willReturn('evil');
        $this->expectException(\RuntimeException::class);
        (new CommunityPublicClipService(
            $reader,
            $media,
            $storage,
            $this->transactions()
        ))->media($clipId);
    }

    /** @return array{clip_id:string,profile_id:string,alias:string,caption:string,language:string,surah:int,start:int,end:int,published_at:string,media_kind:string,comment_policy:string,workspace_id:int,asset_id:UuidV7} */
    private function record(UuidV7 $clipId): array
    {
        return ['clip_id' => $clipId->toString(), 'profile_id' => UuidV7::generate()->toString(),
            'alias' => 'Reciter', 'caption' => 'A passage', 'language' => 'en', 'surah' => 1,
            'start' => 1, 'end' => 2, 'published_at' => '2026-09-22 12:00:00.000000',
            'media_kind' => 'AUDIO', 'comment_policy' => 'ENABLED',
            'workspace_id' => 9, 'asset_id' => UuidV7::generate()];
    }

    private function transactions(): TransactionManager
    {
        $transactions = $this->createStub(TransactionManager::class);
        $transactions->method('transactional')->willReturnCallback(
            static fn (callable $operation): mixed => $operation(),
        );
        return $transactions;
    }
}
