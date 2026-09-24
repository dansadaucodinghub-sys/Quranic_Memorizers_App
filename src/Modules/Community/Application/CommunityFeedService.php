<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Application;

use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Identifier\UuidV7;

/** Deterministic public projection listing with a bounded revalidation scan. */
final readonly class CommunityFeedService
{
    public function __construct(
        private CommunityFeedCandidates $candidates,
        private CommunityPublicClipReader $clips,
        private TransactionManager $transactions,
    ) {
    }

    /** @return array{items:list<array<string,int|string>>,next_cursor:?string,profile_alias:?string} */
    public function page(
        ?int $viewerAccountId,
        ?string $cursor,
        ?string $language,
        ?int $surah,
        bool $followingOnly = false,
        ?UuidV7 $profileId = null
    ): array {
        if ($language !== null && !in_array($language, ['en', 'ar'], true)) {
            throw new \InvalidArgumentException('Feed language filter is invalid.');
        }
        if ($surah !== null && ($surah < 1 || $surah > 114)) {
            throw new \InvalidArgumentException('Feed Surah filter is invalid.');
        }
        [$beforeTime, $beforeId] = self::decodeCursor($cursor);
        return $this->transactions->transactional(function () use (
            $viewerAccountId,
            $beforeTime,
            $beforeId,
            $language,
            $surah,
            $followingOnly,
            $profileId
        ): array {
            $profileAlias = $profileId === null ? null
                : $this->candidates->publicProfileAlias($profileId, $viewerAccountId);
            if ($profileId !== null && $profileAlias === null) {
                return ['items' => [], 'next_cursor' => null, 'profile_alias' => null];
            }
            $candidates = $this->candidates->newest(
                $viewerAccountId,
                $beforeTime,
                $beforeId,
                $language,
                $surah,
                $followingOnly,
                $profileId
            );
            $items = [];
            $last = null;
            $scanned = 0;
            foreach ($candidates as $candidate) {
                if ($scanned === 40) {
                    break;
                }
                ++$scanned;
                $last = $candidate;
                $visible = $this->clips->find($candidate['clip_id'], $viewerAccountId);
                if ($visible === null) {
                    continue;
                }
                $items[] = ['clip_id' => $visible['clip_id'], 'profile_id' => $visible['profile_id'],
                    'alias' => $visible['alias'], 'caption' => $visible['caption'],
                    'language' => $visible['language'], 'surah' => $visible['surah'],
                    'start' => $visible['start'], 'end' => $visible['end'],
                    'published_at' => $visible['published_at'],
                    'media_kind' => $visible['media_kind']];
                if (count($items) === 20) {
                    break;
                }
            }
            $hasMore = $last !== null && ($scanned < count($candidates) || count($candidates) === 41);
            return ['items' => $items,
                'next_cursor' => $hasMore
                    ? self::encodeCursor($last['published_at'], $last['internal_id']) : null,
                'profile_alias' => $profileAlias];
        });
    }

    /** @return array{?string,?int} */
    private static function decodeCursor(?string $cursor): array
    {
        if ($cursor === null || $cursor === '') {
            return [null, null];
        }
        if (strlen($cursor) > 120 || preg_match('/\A[A-Za-z0-9_-]+\z/', $cursor) !== 1) {
            throw new \InvalidArgumentException('Feed cursor is invalid.');
        }
        $decoded = base64_decode(strtr($cursor, '-_', '+/'), true);
        if (
            !is_string($decoded)
            || preg_match('/\A([0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}\.[0-9]{6})\|([1-9][0-9]{0,18})\z/', $decoded, $matches) !== 1
        ) {
            throw new \InvalidArgumentException('Feed cursor is invalid.');
        }
        return [$matches[1], (int) $matches[2]];
    }

    private static function encodeCursor(string $publishedAt, int $id): string
    {
        return rtrim(strtr(base64_encode($publishedAt . '|' . $id), '+/', '-_'), '=');
    }
}
