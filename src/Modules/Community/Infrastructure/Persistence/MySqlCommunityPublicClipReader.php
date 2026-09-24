<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Infrastructure\Persistence;

use PDO;
use PDOStatement;
use Qmdb\Modules\Community\Application\CommunityPublicClipReader;
use Qmdb\Modules\Community\Application\RecitationClipRepository;
use Qmdb\Modules\Community\Domain\ClipStatus;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

/** Public reads recheck current P9, P4, profile, safety, and block authority. */
final readonly class MySqlCommunityPublicClipReader implements CommunityPublicClipReader
{
    public function __construct(
        private DatabaseConnectionProvider $connections,
        private RecitationClipRepository $clips
    ) {
    }

    public function find(UuidV7 $clipId, ?int $viewerAccountId): ?array
    {
        if (!$this->connections->connection()->inTransaction()) {
            throw new \LogicException('Public Clip reads require a consistent transaction.');
        }
        $row = $this->query(
            'SELECT c.workspace_id FROM recitation_clips c WHERE c.public_id=:public',
            ['public' => $clipId->toBinary()],
        )->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        $workspaceId = $this->integer($row, 'workspace_id');
        $clip = $this->clips->lock($workspaceId, $clipId);
        if ($clip === null || $clip->status !== ClipStatus::PUBLISHED || $clip->audience !== 'PUBLIC') {
            return null;
        }
        try {
            $this->clips->publicationEvidence($clip)->assertPubliclyEligible();
        } catch (\DomainException) {
            return null;
        }
        if ($viewerAccountId !== null) {
            $blocked = $this->query(
                'SELECT id FROM community_blocks WHERE revoked_at IS NULL AND ((blocker_account_id=:viewer AND blocked_account_id=:creator) OR (blocker_account_id=:creator2 AND blocked_account_id=:viewer2)) LIMIT 1',
                ['viewer' => $viewerAccountId, 'creator' => $clip->creatorAccountId,
                    'creator2' => $clip->creatorAccountId, 'viewer2' => $viewerAccountId],
            )->fetchColumn();
            if ($blocked !== false) {
                return null;
            }
        }
        $projection = $this->query(
            'SELECT s.creator_public_id,s.media_variant_public_id,s.caption,s.caption_language,s.surah_number,s.start_ayah_number,s.end_ayah_number,s.clip_version,s.checksum,s.published_at,p.alias,a.public_id AS asset_public_id,a.media_kind,c.comment_policy FROM recitation_clip_public_snapshots s JOIN recitation_clips c ON c.id=s.clip_id AND c.workspace_id=s.workspace_id JOIN community_profiles p ON p.person_id=c.creator_person_id AND p.managing_account_id=c.creator_account_id JOIN media_assets a ON a.workspace_id=c.workspace_id AND a.id=c.media_asset_id WHERE s.workspace_id=:workspace AND s.clip_id=:clip AND s.clip_version=c.version ORDER BY s.id DESC LIMIT 1',
            ['workspace' => $workspaceId, 'clip' => $clip->internalId],
        )->fetch(PDO::FETCH_ASSOC);
        if (
            !is_array($projection)
            || !is_string($projection['creator_public_id'])
            || !is_string($projection['media_variant_public_id'])
            || !is_string($projection['caption'])
            || !is_string($projection['caption_language'])
            || !is_string($projection['checksum'])
            || !is_string($projection['published_at'])
            || !is_string($projection['alias'])
            || !is_string($projection['asset_public_id'])
            || !is_string($projection['media_kind'])
            || !is_string($projection['comment_policy'])
        ) {
            return null;
        }
        if (!in_array($projection['media_kind'], ['AUDIO', 'VIDEO'], true)) {
            return null;
        }
        $version = $this->integer($projection, 'clip_version');
        $surah = $this->integer($projection, 'surah_number');
        $start = $this->integer($projection, 'start_ayah_number');
        $end = $this->integer($projection, 'end_ayah_number');
        $checksum = hash('sha256', implode('|', [$clipId->toString(), (string) $version,
            bin2hex($projection['creator_public_id']), bin2hex($projection['media_variant_public_id']),
            $projection['caption'], $projection['caption_language'], (string) $surah,
            (string) $start, (string) $end]), true);
        if (!hash_equals($projection['checksum'], $checksum)) {
            throw new \UnexpectedValueException('Public Clip projection integrity verification failed.');
        }
        return ['clip_id' => $clipId->toString(),
            'profile_id' => UuidV7::fromBinary($projection['creator_public_id'])->toString(),
            'alias' => $projection['alias'], 'caption' => $projection['caption'],
            'language' => $projection['caption_language'], 'surah' => $surah,
            'start' => $start, 'end' => $end, 'published_at' => $projection['published_at'],
            'media_kind' => $projection['media_kind'],
            'comment_policy' => $projection['comment_policy'],
            'workspace_id' => $workspaceId,
            'asset_id' => UuidV7::fromBinary($projection['asset_public_id'])];
    }

    /** @param array<string,int|string> $parameters */
    private function query(string $sql, array $parameters): PDOStatement
    {
        $statement = $this->connections->connection()->prepare($sql);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Could not prepare public Clip read.');
        }
        $statement->execute($parameters);
        return $statement;
    }

    /** @param array<array-key,mixed> $row */
    private function integer(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        return is_int($value) || (is_string($value) && ctype_digit($value))
            ? (int) $value : throw new \UnexpectedValueException('Public Clip projection is malformed.');
    }
}
