<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Application;

use DateTimeImmutable;
use Qmdb\Modules\Community\Domain\ClipPublicationEvidence;
use Qmdb\Modules\Community\Domain\ClipStatus;
use Qmdb\Modules\Community\Domain\RecitationClip;
use Qmdb\Shared\Identifier\UuidV7;

interface RecitationClipRepository
{
    /** @return list<array{public_id:string,status:string,version:int,caption:string,language:string,comment_policy:string,created_at:string,supersedes_public_id:?string}> */
    public function listOwn(int $workspaceId, int $actorAccountId): array;

    /** @return list<array{public_id:string,asset_id:string,version:int,caption:string,language:string,created_at:string,surah:int,start:int,end:int,supersedes_public_id:?string}> */
    public function reviewQueue(int $workspaceId, int $reviewerAccountId): array;

    /** @return list<array{asset_id:string,variant_id:string,media_kind:string}> */
    public function eligibleMedia(int $workspaceId, int $actorAccountId): array;

    /** @return list<array{release_id:string,surah_number:int,ayah_count:int,arabic_name:string,english_name:string}> */
    public function availablePassages(): array;

    /** @return array{start:UuidV7,end:UuidV7} */
    public function resolvePassage(UuidV7 $releaseId, int $surahNumber, int $startNumber, int $endNumber): array;

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
    ): RecitationClip;

    public function lock(int $workspaceId, UuidV7 $clipId): ?RecitationClip;

    public function publicationEvidence(RecitationClip $clip): ClipPublicationEvidence;

    public function supersededSourceId(RecitationClip $replacement): ?UuidV7;

    public function updateDraft(
        RecitationClip $clip,
        int $actorAccountId,
        string $caption,
        string $language,
        DateTimeImmutable $now,
        string $commentPolicy = 'DISABLED'
    ): RecitationClip;

    public function transition(
        RecitationClip $clip,
        ClipStatus $target,
        int $actorAccountId,
        string $reasonCode,
        DateTimeImmutable $now,
    ): RecitationClip;
}
