<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOStatement;
use Qmdb\Modules\Community\Application\RecitationClipRepository;
use Qmdb\Modules\Community\Domain\ClipPublicationEvidence;
use Qmdb\Modules\Community\Domain\ClipStatus;
use Qmdb\Modules\Community\Domain\RecitationClip;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

/** All decisions use current tenant-scoped rows inside the caller's transaction. */
final readonly class MySqlRecitationClipRepository implements RecitationClipRepository
{
    public function __construct(private DatabaseConnectionProvider $connections)
    {
    }

    public function listOwn(int $workspaceId, int $actorAccountId): array
    {
        $rows = $this->execute(
            'SELECT c.public_id,c.status,c.version,c.caption,c.caption_language,c.comment_policy,c.created_at,source.public_id AS supersedes_public FROM recitation_clips c LEFT JOIN recitation_clips source ON source.workspace_id=c.workspace_id AND source.id=c.supersedes_clip_id WHERE c.workspace_id=:workspace AND c.creator_account_id=:actor ORDER BY c.created_at DESC,c.id DESC LIMIT 50',
            ['workspace' => $workspaceId, 'actor' => $actorAccountId]
        )->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            if (
                !is_array($row) || !is_string($row['public_id'] ?? null)
                || !is_string($row['status'] ?? null) || !is_string($row['caption'] ?? null)
                || !is_string($row['caption_language'] ?? null) || !is_string($row['comment_policy'] ?? null)
                || !is_string($row['created_at'] ?? null)
            ) {
                throw new \UnexpectedValueException('Creator Clip row is malformed.');
            }
            $result[] = ['public_id' => UuidV7::fromBinary($row['public_id'])->toString(),
                'status' => $row['status'], 'version' => $this->integer($row, 'version'),
                'caption' => $row['caption'], 'language' => $row['caption_language'],
                'comment_policy' => $row['comment_policy'],
                'created_at' => $row['created_at'],
                'supersedes_public_id' => is_string($row['supersedes_public'] ?? null)
                    ? UuidV7::fromBinary($row['supersedes_public'])->toString() : null];
        }
        return $result;
    }

    public function reviewQueue(int $workspaceId, int $reviewerAccountId): array
    {
        $rows = $this->execute(
            "SELECT c.public_id,a.public_id AS asset_public,c.version,c.caption,c.caption_language,c.created_at,s.surah_number,sa.ayah_number AS start_number,ea.ayah_number AS end_number,source.public_id AS supersedes_public FROM recitation_clips c JOIN media_assets a ON a.workspace_id=c.workspace_id AND a.id=c.media_asset_id LEFT JOIN recitation_clips source ON source.workspace_id=c.workspace_id AND source.id=c.supersedes_clip_id JOIN recitation_clip_quran_references q ON q.workspace_id=c.workspace_id AND q.clip_id=c.id AND q.reference_order=1 JOIN quran_surahs s ON s.id=q.surah_id JOIN quran_ayahs sa ON sa.id=q.start_ayah_id JOIN quran_ayahs ea ON ea.id=q.end_ayah_id WHERE c.workspace_id=:workspace AND c.status='REVIEW_PENDING' AND c.creator_account_id<>:reviewer ORDER BY c.submitted_at ASC,c.id ASC LIMIT 50",
            ['workspace' => $workspaceId, 'reviewer' => $reviewerAccountId]
        )->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            if (
                !is_array($row) || !is_string($row['public_id'] ?? null)
                || !is_string($row['asset_public'] ?? null)
                || !is_string($row['caption'] ?? null) || !is_string($row['caption_language'] ?? null)
                || !is_string($row['created_at'] ?? null)
            ) {
                throw new \UnexpectedValueException('Clip review row is malformed.');
            }
            $result[] = ['public_id' => UuidV7::fromBinary($row['public_id'])->toString(),
                'asset_id' => UuidV7::fromBinary($row['asset_public'])->toString(),
                'version' => $this->integer($row, 'version'), 'caption' => $row['caption'],
                'language' => $row['caption_language'], 'created_at' => $row['created_at'],
                'surah' => $this->integer($row, 'surah_number'),
                'start' => $this->integer($row, 'start_number'),
                'end' => $this->integer($row, 'end_number'),
                'supersedes_public_id' => is_string($row['supersedes_public'] ?? null)
                    ? UuidV7::fromBinary($row['supersedes_public'])->toString() : null];
        }
        return $result;
    }

    public function eligibleMedia(int $workspaceId, int $actorAccountId): array
    {
        $rows = $this->execute(<<<'SQL'
SELECT a.public_id AS asset_public,v.public_id AS variant_public,a.media_kind
FROM media_assets a
JOIN media_variants v ON v.workspace_id=a.workspace_id AND v.asset_id=a.id
JOIN people_account_links l ON l.person_id=a.owner_person_id AND l.account_id=:actor
  AND l.link_type='SELF' AND l.status='ACTIVE'
JOIN people_persons p ON p.id=a.owner_person_id AND p.status='ACTIVE'
JOIN media_delivery_policies d ON d.workspace_id=a.workspace_id AND d.asset_id=a.id
WHERE a.workspace_id=:workspace AND a.created_by_account_id=:creator
  AND a.status IN ('APPROVED','PUBLISHED') AND a.media_kind IN ('AUDIO','VIDEO')
  AND v.status='READY' AND v.variant_code='NORMALIZED_V1' AND v.is_public_safe=1
  AND d.rights_granted=1 AND d.consent_granted=1 AND d.visibility_code='PUBLIC'
  AND EXISTS (SELECT 1 FROM media_scan_results s WHERE s.workspace_id=a.workspace_id AND s.asset_id=a.id AND s.result_code='CLEAN')
  AND NOT EXISTS (SELECT 1 FROM media_holds h WHERE h.workspace_id=a.workspace_id AND h.asset_id=a.id AND h.released_at IS NULL)
ORDER BY a.id DESC,v.id DESC LIMIT 50
SQL, ['workspace' => $workspaceId, 'actor' => $actorAccountId, 'creator' => $actorAccountId])->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            if (
                !is_array($row) || !is_string($row['asset_public'] ?? null)
                || !is_string($row['variant_public'] ?? null) || !is_string($row['media_kind'] ?? null)
            ) {
                throw new \UnexpectedValueException('Eligible Clip media row is malformed.');
            }
            $result[] = ['asset_id' => UuidV7::fromBinary($row['asset_public'])->toString(),
                'variant_id' => UuidV7::fromBinary($row['variant_public'])->toString(),
                'media_kind' => $row['media_kind']];
        }
        return $result;
    }

    public function availablePassages(): array
    {
        $rows = $this->execute(<<<'SQL'
SELECT r.public_id AS release_public,s.surah_number,s.ayah_count,s.arabic_name,
  COALESCE(s.english_name,s.transliterated_name,s.arabic_name) AS english_name
FROM quran_reference_releases r
JOIN quran_surahs s ON s.release_id=r.id
WHERE r.status='ACTIVE' ORDER BY s.surah_number LIMIT 114
SQL, [])->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            if (
                !is_array($row) || !is_string($row['release_public'] ?? null)
                || !is_string($row['arabic_name'] ?? null) || !is_string($row['english_name'] ?? null)
            ) {
                throw new \UnexpectedValueException('Canonical passage row is malformed.');
            }
            $result[] = ['release_id' => UuidV7::fromBinary($row['release_public'])->toString(),
                'surah_number' => $this->integer($row, 'surah_number'),
                'ayah_count' => $this->integer($row, 'ayah_count'),
                'arabic_name' => $row['arabic_name'], 'english_name' => $row['english_name']];
        }
        return $result;
    }

    public function resolvePassage(UuidV7 $releaseId, int $surahNumber, int $startNumber, int $endNumber): array
    {
        if ($surahNumber < 1 || $surahNumber > 114 || $startNumber < 1 || $endNumber < $startNumber) {
            throw new \InvalidArgumentException('Canonical passage bounds are invalid.');
        }
        $row = $this->row(<<<'SQL'
SELECT sa.public_id AS start_public,ea.public_id AS end_public
FROM quran_reference_releases r
JOIN quran_ayahs sa ON sa.release_id=r.id AND sa.surah_number=:surah AND sa.ayah_number=:start
JOIN quran_ayahs ea ON ea.release_id=r.id AND ea.surah_number=sa.surah_number AND ea.ayah_number=:end
WHERE r.public_id=:release AND r.status='ACTIVE' LIMIT 1
SQL, ['surah' => $surahNumber, 'start' => $startNumber, 'end' => $endNumber,
            'release' => $releaseId->toBinary()]);
        if ($row === null || !is_string($row['start_public'] ?? null) || !is_string($row['end_public'] ?? null)) {
            throw new \DomainException('Canonical passage is unavailable.');
        }
        return ['start' => UuidV7::fromBinary($row['start_public']),
            'end' => UuidV7::fromBinary($row['end_public'])];
    }

    public function createDraft(
        int $workspaceId,
        int $actorAccountId,
        UuidV7 $assetId,
        UuidV7 $variantId,
        UuidV7 $releaseId,
        UuidV7 $startAyahId,
        UuidV7 $endAyahId,
        string $caption,
        string $language,
        DateTimeImmutable $now,
        ?UuidV7 $supersedesClipId = null,
    ): RecitationClip {
        $this->requireTransaction();
        $asset = $this->row(
            'SELECT a.id,a.owner_person_id,a.created_by_account_id,a.status,a.media_kind,v.id AS variant_id,v.status AS variant_status,v.variant_code,v.is_public_safe '
            . 'FROM media_assets a JOIN media_variants v ON v.workspace_id=a.workspace_id AND v.asset_id=a.id '
            . 'WHERE a.workspace_id=:workspace AND a.public_id=:asset AND v.public_id=:variant FOR UPDATE',
            ['workspace' => $workspaceId, 'asset' => $assetId->toBinary(), 'variant' => $variantId->toBinary()],
        );
        if (
            $asset === null || !in_array($asset['status'], ['APPROVED', 'PUBLISHED'], true)
            || !in_array($asset['media_kind'], ['AUDIO', 'VIDEO'], true) || $asset['variant_status'] !== 'READY'
            || $asset['variant_code'] !== 'NORMALIZED_V1' || $this->integer($asset, 'is_public_safe') !== 1
            || $this->integer($asset, 'created_by_account_id') !== $actorAccountId || $asset['owner_person_id'] === null
        ) {
            throw new \DomainException('An approved, owned audio or video asset and ready variant are required.');
        }
        $personId = $this->integer($asset, 'owner_person_id');
        $person = $this->row(
            "SELECT p.id FROM people_persons p JOIN people_account_links l ON l.person_id=p.id AND l.account_id=:actor AND l.status='ACTIVE' AND l.link_type='SELF' WHERE p.id=:person AND p.status='ACTIVE' FOR UPDATE",
            ['actor' => $actorAccountId, 'person' => $personId],
        );
        if ($person === null) {
            throw new \DomainException('Clip creator requires an active self-linked Person.');
        }
        $reference = $this->reference($releaseId, $startAyahId, $endAyahId);
        $supersedesInternalId = null;
        if ($supersedesClipId !== null) {
            $source = $this->lock($workspaceId, $supersedesClipId);
            if (
                $source === null || $source->status !== ClipStatus::PUBLISHED
                || $source->creatorAccountId !== $actorAccountId
                || $source->creatorPersonId !== $personId
            ) {
                throw new \DomainException('A current published Clip by this creator is required for supersession.');
            }
            $existing = $this->row(
                "SELECT id FROM recitation_clips WHERE workspace_id=:workspace
                 AND supersedes_clip_id=:source AND status<>'ARCHIVED' LIMIT 1 FOR UPDATE",
                ['workspace' => $workspaceId, 'source' => $source->internalId]
            );
            if ($existing !== null) {
                throw new \DomainException('This Clip already has an active replacement.');
            }
            $supersedesInternalId = $source->internalId;
        }
        $publicId = UuidV7::generate();
        $time = $this->time($now);
        $this->execute(
            "INSERT INTO recitation_clips (public_id,workspace_id,creator_account_id,creator_person_id,media_asset_id,media_variant_id,caption,caption_language,status,audience,comment_policy,supersedes_clip_id,version,created_at,updated_at) VALUES (:public,:workspace,:actor,:person,:asset,:variant,:caption,:language,'DRAFT','PRIVATE','DISABLED',:supersedes,1,:created,:updated)",
            ['public' => $publicId->toBinary(), 'workspace' => $workspaceId, 'actor' => $actorAccountId,
                'person' => $personId, 'asset' => $this->integer($asset, 'id'), 'variant' => $this->integer($asset, 'variant_id'),
                'caption' => $caption, 'language' => $language, 'supersedes' => $supersedesInternalId,
                'created' => $time, 'updated' => $time],
        );
        $clipId = (int) $this->connections->connection()->lastInsertId();
        $this->execute(
            'INSERT INTO recitation_clip_quran_references (workspace_id,clip_id,reference_order,release_id,surah_id,start_ayah_id,end_ayah_id,created_at) VALUES (:workspace,:clip,1,:release,:surah,:start,:end,:created)',
            ['workspace' => $workspaceId, 'clip' => $clipId, 'release' => $reference['release_id'],
                'surah' => $reference['surah_id'], 'start' => $reference['start_id'],
                'end' => $reference['end_id'], 'created' => $time],
        );
        $this->event($workspaceId, $clipId, $actorAccountId, 'CREATED', null, ClipStatus::DRAFT, null, 1, $time);
        return new RecitationClip(
            $clipId,
            $publicId,
            $workspaceId,
            $actorAccountId,
            $personId,
            $this->integer($asset, 'id'),
            ClipStatus::DRAFT,
            'PRIVATE',
            1
        );
    }

    public function lock(int $workspaceId, UuidV7 $clipId): ?RecitationClip
    {
        $this->requireTransaction();
        $row = $this->row(
            'SELECT id,public_id,workspace_id,creator_account_id,creator_person_id,media_asset_id,status,audience,version FROM recitation_clips WHERE workspace_id=:workspace AND public_id=:public FOR UPDATE',
            ['workspace' => $workspaceId, 'public' => $clipId->toBinary()],
        );
        if ($row === null) {
            return null;
        }
        if (!is_string($row['public_id']) || !is_string($row['status']) || !is_string($row['audience'])) {
            throw new \UnexpectedValueException('Clip row is malformed.');
        }
        return new RecitationClip(
            $this->integer($row, 'id'),
            UuidV7::fromBinary($row['public_id']),
            $this->integer($row, 'workspace_id'),
            $this->integer($row, 'creator_account_id'),
            $this->integer($row, 'creator_person_id'),
            $this->integer($row, 'media_asset_id'),
            ClipStatus::from($row['status']),
            $row['audience'],
            $this->integer($row, 'version')
        );
    }

    public function publicationEvidence(RecitationClip $clip): ClipPublicationEvidence
    {
        $this->requireTransaction();
        $row = $this->row(
            <<<'SQL'
SELECT c.creator_account_id,c.creator_person_id,c.media_asset_id,
 p.status AS person_status,account.account_status AS account_status,
 (p.birth_date IS NOT NULL AND p.birth_date<=DATE_SUB(UTC_DATE(), INTERVAL 18 YEAR)) AS verified_adult,
 l.id AS self_link_id,cp.id AS profile_id,cp.visibility AS profile_visibility,
 cp.status AS profile_status,cp.public_consent AS profile_consent,a.owner_person_id,a.created_by_account_id,a.status AS asset_status,
 v.status AS variant_status,v.is_public_safe,s.result_code AS scan_result,
 d.rights_granted,d.consent_granted,d.visibility_code,
 cr.reviewer_account_id,cr.participant_count,cr.consent_count,cr.minor_count,cr.guardian_consent_count,
 EXISTS(SELECT 1 FROM media_holds h WHERE h.workspace_id=c.workspace_id AND h.asset_id=c.media_asset_id AND h.released_at IS NULL) AS held,
 EXISTS(SELECT 1 FROM community_moderation_cases mc WHERE mc.workspace_id=c.workspace_id AND mc.clip_id=c.id AND mc.priority_code='CHILD_SAFETY' AND mc.status NOT IN ('DISMISSED','CLOSED')) AS emergency_case,
 r.status AS release_status,sa.ayah_number AS start_number,ea.ayah_number AS end_number,
 sa.release_id AS start_release,ea.release_id AS end_release,sa.surah_id AS start_surah,ea.surah_id AS end_surah
FROM recitation_clips c
JOIN people_persons p ON p.id=c.creator_person_id
JOIN user_accounts account ON account.id=c.creator_account_id
LEFT JOIN people_account_links l ON l.person_id=c.creator_person_id AND l.account_id=c.creator_account_id AND l.status='ACTIVE' AND l.link_type='SELF'
LEFT JOIN community_profiles cp ON cp.person_id=c.creator_person_id AND cp.managing_account_id=c.creator_account_id
JOIN media_assets a ON a.workspace_id=c.workspace_id AND a.id=c.media_asset_id
JOIN media_variants v ON v.workspace_id=c.workspace_id AND v.asset_id=a.id AND v.id=c.media_variant_id
LEFT JOIN media_scan_results s ON s.workspace_id=c.workspace_id AND s.asset_id=a.id
LEFT JOIN media_delivery_policies d ON d.workspace_id=c.workspace_id AND d.asset_id=a.id
LEFT JOIN media_consent_reviews cr ON cr.id=(SELECT cr2.id FROM media_consent_reviews cr2 WHERE cr2.workspace_id=c.workspace_id AND cr2.asset_id=a.id AND cr2.asset_version<=a.version ORDER BY cr2.id DESC LIMIT 1)
JOIN recitation_clip_quran_references q ON q.workspace_id=c.workspace_id AND q.clip_id=c.id AND q.reference_order=1
JOIN quran_reference_releases r ON r.id=q.release_id
JOIN quran_ayahs sa ON sa.id=q.start_ayah_id
JOIN quran_ayahs ea ON ea.id=q.end_ayah_id
WHERE c.workspace_id=:workspace AND c.id=:clip FOR UPDATE
SQL,
            ['workspace' => $clip->workspaceId, 'clip' => $clip->internalId],
        );
        if ($row === null) {
            throw new \DomainException('Clip publication evidence is incomplete.');
        }
        return new ClipPublicationEvidence(
            $row['person_status'] === 'ACTIVE' && $row['account_status'] === 'ACTIVE',
            $row['self_link_id'] !== null,
            $row['profile_id'] !== null && $row['profile_status'] === 'ACTIVE'
                && $this->integer($row, 'verified_adult') === 1
                && $row['profile_visibility'] === 'PUBLIC' && $this->integer($row, 'profile_consent') === 1,
            $row['owner_person_id'] !== null && $this->integer($row, 'owner_person_id') === $clip->creatorPersonId
                && $this->integer($row, 'created_by_account_id') === $clip->creatorAccountId,
            in_array($row['asset_status'], ['APPROVED', 'PUBLISHED'], true),
            $row['scan_result'] === 'CLEAN',
            $row['variant_status'] === 'READY',
            $this->integer($row, 'is_public_safe') === 1,
            $row['rights_granted'] !== null && $this->integer($row, 'rights_granted') === 1,
            $row['consent_granted'] !== null && $this->integer($row, 'consent_granted') === 1,
            $row['reviewer_account_id'] !== null && $this->integer($row, 'reviewer_account_id') !== $clip->creatorAccountId
                && $row['consent_count'] !== null && $this->integer($row, 'consent_count') === $this->integer($row, 'participant_count'),
            $row['minor_count'] !== null && $this->integer($row, 'minor_count') === $this->integer($row, 'guardian_consent_count'),
            $this->integer($row, 'held') === 1,
            $row['visibility_code'] === 'PUBLIC',
            $row['release_status'] === 'ACTIVE',
            $row['start_release'] !== null && $this->integer($row, 'start_release') === $this->integer($row, 'end_release')
                && $this->integer($row, 'start_surah') === $this->integer($row, 'end_surah')
                && $this->integer($row, 'start_number') <= $this->integer($row, 'end_number'),
            $this->integer($row, 'emergency_case') === 0,
        );
    }

    public function transition(RecitationClip $clip, ClipStatus $target, int $actorAccountId, string $reasonCode, DateTimeImmutable $now): RecitationClip
    {
        $this->requireTransaction();
        $source = null;
        if ($target === ClipStatus::PUBLISHED && $reasonCode === 'INDEPENDENT_REVIEW') {
            $sourceId = $this->supersededSourceId($clip);
            if ($sourceId !== null) {
                $source = $this->lock($clip->workspaceId, $sourceId);
                if (
                    $source === null || $source->status !== ClipStatus::PUBLISHED
                    || $source->creatorAccountId !== $clip->creatorAccountId
                    || $source->creatorPersonId !== $clip->creatorPersonId
                ) {
                    throw new \DomainException('The Clip being replaced changed before publication.');
                }
                $source->status->assertTransition(ClipStatus::SUPERSEDED);
            }
        }
        $time = $this->time($now);
        $timestampColumn = match ($target) {
            ClipStatus::REVIEW_PENDING => 'submitted_at',
            ClipStatus::PUBLISHED => 'published_at',
            ClipStatus::HIDDEN => 'hidden_at',
            ClipStatus::REMOVED => 'removed_at',
            ClipStatus::ARCHIVED => 'archived_at',
            default => null,
        };
        $sets = ['status=:target', 'version=version+1', 'updated_at=:now'];
        if ($timestampColumn !== null) {
            $sets[] = $timestampColumn . '=:time';
        }
        if ($target === ClipStatus::PUBLISHED) {
            $sets[] = "audience='PUBLIC'";
            $sets[] = "publication_policy_version='P10_V1'";
        }
        $parameters = ['target' => $target->value, 'now' => $time, 'workspace' => $clip->workspaceId,
            'clip' => $clip->internalId, 'version' => $clip->version, 'old' => $clip->status->value];
        if ($timestampColumn !== null) {
            $parameters['time'] = $time;
        }
        $updated = $this->execute('UPDATE recitation_clips SET ' . implode(',', $sets)
            . ' WHERE workspace_id=:workspace AND id=:clip AND version=:version AND status=:old', $parameters);
        if ($updated->rowCount() !== 1) {
            throw new \DomainException('Clip changed concurrently.');
        }
        $version = $clip->version + 1;
        $this->event(
            $clip->workspaceId,
            $clip->internalId,
            $actorAccountId,
            $reasonCode,
            $clip->status,
            $target,
            $reasonCode,
            $version,
            $time
        );
        if ($target === ClipStatus::PUBLISHED) {
            $this->snapshot($clip, $version, $time);
        }
        if ($source !== null) {
            $this->transition(
                $source,
                ClipStatus::SUPERSEDED,
                $actorAccountId,
                'REPLACED_BY_PUBLISHED_CLIP',
                $now
            );
        }
        return new RecitationClip(
            $clip->internalId,
            $clip->publicId,
            $clip->workspaceId,
            $clip->creatorAccountId,
            $clip->creatorPersonId,
            $clip->mediaAssetId,
            $target,
            $target === ClipStatus::PUBLISHED ? 'PUBLIC' : $clip->audience,
            $version
        );
    }

    public function supersededSourceId(RecitationClip $replacement): ?UuidV7
    {
        $this->requireTransaction();
        $row = $this->row(
            'SELECT source.public_id FROM recitation_clips replacement
             JOIN recitation_clips source ON source.workspace_id=replacement.workspace_id
               AND source.id=replacement.supersedes_clip_id
             WHERE replacement.workspace_id=:workspace AND replacement.id=:replacement',
            ['workspace' => $replacement->workspaceId, 'replacement' => $replacement->internalId]
        );
        return $row !== null && is_string($row['public_id'] ?? null)
            ? UuidV7::fromBinary($row['public_id']) : null;
    }

    public function updateDraft(
        RecitationClip $clip,
        int $actorAccountId,
        string $caption,
        string $language,
        DateTimeImmutable $now,
        string $commentPolicy = 'DISABLED'
    ): RecitationClip {
        $this->requireTransaction();
        if ($clip->status !== ClipStatus::DRAFT || $clip->creatorAccountId !== $actorAccountId) {
            throw new \DomainException('Only the creator may edit a draft Clip.');
        }
        if (!in_array($commentPolicy, ['DISABLED', 'REVIEW', 'ENABLED'], true)) {
            throw new \InvalidArgumentException('Clip comment policy is invalid.');
        }
        $time = $this->time($now);
        $updated = $this->execute(
            "UPDATE recitation_clips SET caption=:caption,caption_language=:language,comment_policy=:comments,version=version+1,updated_at=:updated WHERE workspace_id=:workspace AND id=:clip AND status='DRAFT' AND version=:version",
            ['caption' => $caption, 'language' => $language, 'comments' => $commentPolicy, 'updated' => $time,
            'workspace' => $clip->workspaceId,
            'clip' => $clip->internalId,
            'version' => $clip->version]
        );
        if ($updated->rowCount() !== 1) {
            throw new \DomainException('Clip changed concurrently.');
        }
        $this->event(
            $clip->workspaceId,
            $clip->internalId,
            $actorAccountId,
            'METADATA_UPDATED',
            ClipStatus::DRAFT,
            ClipStatus::DRAFT,
            'METADATA_UPDATED',
            $clip->version + 1,
            $time
        );
        return new RecitationClip(
            $clip->internalId,
            $clip->publicId,
            $clip->workspaceId,
            $clip->creatorAccountId,
            $clip->creatorPersonId,
            $clip->mediaAssetId,
            ClipStatus::DRAFT,
            $clip->audience,
            $clip->version + 1
        );
    }

    /** @return array{release_id:int,surah_id:int,start_id:int,end_id:int} */
    private function reference(UuidV7 $release, UuidV7 $start, UuidV7 $end): array
    {
        $row = $this->row(
            "SELECT r.id AS release_id,sa.surah_id,sa.id AS start_id,ea.id AS end_id FROM quran_reference_releases r JOIN quran_ayahs sa ON sa.release_id=r.id JOIN quran_ayahs ea ON ea.release_id=r.id AND ea.surah_id=sa.surah_id WHERE r.public_id=:release AND r.status='ACTIVE' AND sa.public_id=:start AND ea.public_id=:end AND sa.ayah_number<=ea.ayah_number FOR UPDATE",
            ['release' => $release->toBinary(), 'start' => $start->toBinary(), 'end' => $end->toBinary()],
        );
        if ($row === null) {
            throw new \DomainException('An active canonical Qur’an release and valid passage are required.');
        }
        return ['release_id' => $this->integer($row, 'release_id'), 'surah_id' => $this->integer($row, 'surah_id'),
            'start_id' => $this->integer($row, 'start_id'), 'end_id' => $this->integer($row, 'end_id')];
    }

    private function snapshot(RecitationClip $clip, int $version, string $time): void
    {
        $row = $this->row(
            'SELECT p.public_id AS creator_public,v.public_id AS variant_public,c.caption,c.caption_language,s.surah_number,sa.ayah_number AS start_number,ea.ayah_number AS end_number FROM recitation_clips c JOIN community_profiles p ON p.person_id=c.creator_person_id AND p.managing_account_id=c.creator_account_id JOIN media_variants v ON v.id=c.media_variant_id JOIN recitation_clip_quran_references q ON q.clip_id=c.id AND q.reference_order=1 JOIN quran_surahs s ON s.id=q.surah_id JOIN quran_ayahs sa ON sa.id=q.start_ayah_id JOIN quran_ayahs ea ON ea.id=q.end_ayah_id WHERE c.workspace_id=:workspace AND c.id=:clip',
            ['workspace' => $clip->workspaceId, 'clip' => $clip->internalId],
        );
        if (
            $row === null || !is_string($row['creator_public']) || !is_string($row['variant_public'])
            || !is_string($row['caption']) || !is_string($row['caption_language'])
        ) {
            throw new \UnexpectedValueException('Clip public projection is incomplete.');
        }
        $checksum = hash('sha256', implode('|', [$clip->publicId->toString(), (string) $version,
            bin2hex($row['creator_public']), bin2hex($row['variant_public']), $row['caption'],
            $row['caption_language'], (string) $this->integer($row, 'surah_number'),
            (string) $this->integer($row, 'start_number'), (string) $this->integer($row, 'end_number')]), true);
        $this->execute(
            'INSERT INTO recitation_clip_public_snapshots (public_id,workspace_id,clip_id,clip_version,creator_public_id,media_variant_public_id,caption,caption_language,surah_number,start_ayah_number,end_ayah_number,checksum,published_at) VALUES (:public,:workspace,:clip,:version,:creator,:variant,:caption,:language,:surah,:start,:end,:checksum,:published)',
            ['public' => UuidV7::generate()->toBinary(), 'workspace' => $clip->workspaceId,
                'clip' => $clip->internalId, 'version' => $version, 'creator' => $row['creator_public'],
                'variant' => $row['variant_public'], 'caption' => $row['caption'],
                'language' => $row['caption_language'], 'surah' => $this->integer($row, 'surah_number'),
                'start' => $this->integer($row, 'start_number'), 'end' => $this->integer($row, 'end_number'),
                'checksum' => $checksum, 'published' => $time],
        );
    }

    private function event(
        int $workspace,
        int $clip,
        int $actor,
        string $event,
        ?ClipStatus $from,
        ClipStatus $to,
        ?string $reason,
        int $version,
        string $time
    ): void {
        $this->execute(
            'INSERT INTO recitation_clip_events (public_id,workspace_id,clip_id,actor_account_id,event_code,from_status,to_status,reason_code,clip_version,created_at) VALUES (:public,:workspace,:clip,:actor,:event,:old,:target,:reason,:version,:created)',
            ['public' => UuidV7::generate()->toBinary(), 'workspace' => $workspace, 'clip' => $clip,
                'actor' => $actor, 'event' => $event, 'old' => $from?->value, 'target' => $to->value,
                'reason' => $reason, 'version' => $version, 'created' => $time],
        );
    }

    /** @param array<string,int|string|null> $parameters */
    private function execute(string $sql, array $parameters): PDOStatement
    {
        $statement = $this->connections->connection()->prepare($sql);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Could not prepare Clip statement.');
        }
        $statement->execute($parameters);
        return $statement;
    }

    /** @param array<string,int|string|null> $parameters
     * @return array<array-key,mixed>|null
     */
    private function row(string $sql, array $parameters): ?array
    {
        $row = $this->execute($sql, $parameters)->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @param array<array-key,mixed> $row */
    private function integer(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        return is_int($value) || (is_string($value) && ctype_digit($value))
            ? (int) $value : throw new \UnexpectedValueException('Clip evidence is malformed.');
    }

    private function requireTransaction(): void
    {
        if (!$this->connections->connection()->inTransaction()) {
            throw new \LogicException('Clip mutations require a transaction.');
        }
    }

    private function time(DateTimeImmutable $now): string
    {
        return $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }
}
