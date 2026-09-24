<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Application;

use Qmdb\Modules\MediaCatalog\Application\MediaEvidenceRepository;
use Qmdb\Modules\MediaIngestion\Application\MediaBlobStore;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Identifier\UuidV7;

/** Only this boundary turns an internal public-read candidate into public fields or media bytes. */
final readonly class CommunityPublicClipService
{
    public function __construct(
        private CommunityPublicClipReader $reader,
        private MediaEvidenceRepository $media,
        private MediaBlobStore $storage,
        private TransactionManager $transactions,
    ) {
    }

    /** @return array{clip_id:string,profile_id:string,alias:string,caption:string,language:string,surah:int,start:int,end:int,published_at:string,media_kind:string,comment_policy:string,media_url:string}|null */
    public function detail(UuidV7 $clipId, ?int $viewerAccountId = null): ?array
    {
        return $this->transactions->transactional(function () use ($clipId, $viewerAccountId): ?array {
            $record = $this->reader->find($clipId, $viewerAccountId);
            if ($record === null) {
                return null;
            }
            return ['clip_id' => $record['clip_id'], 'profile_id' => $record['profile_id'],
                'alias' => $record['alias'], 'caption' => $record['caption'],
                'language' => $record['language'], 'surah' => $record['surah'],
                'start' => $record['start'], 'end' => $record['end'],
                'published_at' => $record['published_at'],
                'media_kind' => $record['media_kind'], 'comment_policy' => $record['comment_policy'],
                'media_url' => '/clips/' . $record['clip_id'] . '/media'];
        });
    }

    /** @return array{contents:string,mime_type:string,sha256:string}|null */
    public function media(UuidV7 $clipId, ?int $viewerAccountId = null): ?array
    {
        return $this->transactions->transactional(function () use ($clipId, $viewerAccountId): ?array {
            $record = $this->reader->find($clipId, $viewerAccountId);
            if ($record === null) {
                return null;
            }
            $delivery = $this->media->findDeliverable($record['workspace_id'], $record['asset_id']);
            if (
                $delivery === null || $delivery['byte_size'] < 1 || $delivery['byte_size'] > 16_777_216
                || !in_array($delivery['mime_type'], ['audio/mpeg', 'video/mp4'], true)
            ) {
                return null;
            }
            $contents = $this->storage->get($delivery['storage_key']);
            if (
                strlen($contents) !== $delivery['byte_size']
                || !hash_equals($delivery['sha256'], hash('sha256', $contents, true))
            ) {
                throw new \RuntimeException('Public Clip media integrity verification failed.');
            }
            return ['contents' => $contents, 'mime_type' => $delivery['mime_type'],
                'sha256' => $delivery['sha256']];
        });
    }
}
