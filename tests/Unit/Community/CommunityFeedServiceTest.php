<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Community;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\Community\Application\CommunityFeedCandidates;
use Qmdb\Modules\Community\Application\CommunityFeedService;
use Qmdb\Modules\Community\Application\CommunityPublicClipReader;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Identifier\UuidV7;

final class CommunityFeedServiceTest extends TestCase
{
    public function testOnlyCurrentlyEligiblePublicClipFieldsAreReturned(): void
    {
        $hidden = UuidV7::generate();
        $visible = UuidV7::generate();
        $candidates = $this->createStub(CommunityFeedCandidates::class);
        $candidates->method('newest')->willReturn([
            ['clip_id' => $hidden, 'published_at' => '2026-09-22 12:00:01.000000', 'internal_id' => 2],
            ['clip_id' => $visible, 'published_at' => '2026-09-22 12:00:00.000000', 'internal_id' => 1],
        ]);
        $reader = $this->createMock(CommunityPublicClipReader::class);
        $reader->expects(self::exactly(2))->method('find')->willReturnCallback(
            static fn (UuidV7 $clipId, ?int $viewerId): ?array => $clipId->toString() === $visible->toString()
                ? ['clip_id' => $visible->toString(), 'profile_id' => UuidV7::generate()->toString(),
                    'alias' => 'Reciter', 'caption' => 'A passage', 'language' => 'en',
                    'surah' => 1, 'start' => 1, 'end' => 2,
                    'published_at' => '2026-09-22 12:00:00.000000', 'media_kind' => 'AUDIO',
                    'comment_policy' => 'ENABLED',
                    'workspace_id' => 9, 'asset_id' => UuidV7::generate()]
                : null,
        );
        $page = (new CommunityFeedService($candidates, $reader, $this->transactions()))->page(7, null, null, null);
        self::assertCount(1, $page['items']);
        self::assertSame($visible->toString(), $page['items'][0]['clip_id']);
        self::assertArrayNotHasKey('workspace_id', $page['items'][0]);
        self::assertArrayNotHasKey('asset_id', $page['items'][0]);
        self::assertNull($page['next_cursor']);
    }

    public function testMalformedCursorIsRejectedBeforeDatabaseRead(): void
    {
        $candidates = $this->createMock(CommunityFeedCandidates::class);
        $candidates->expects(self::never())->method('newest');
        $service = new CommunityFeedService(
            $candidates,
            $this->createStub(CommunityPublicClipReader::class),
            $this->transactions()
        );
        $this->expectException(\InvalidArgumentException::class);
        $service->page(null, '../../private', null, null);
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
