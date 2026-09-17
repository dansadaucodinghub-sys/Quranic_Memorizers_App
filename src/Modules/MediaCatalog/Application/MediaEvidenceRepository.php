<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaCatalog\Application;

use DateTimeImmutable;
use Qmdb\Shared\Identifier\UuidV7;

/** Persistence contract for P9’s private evidence aggregate. */
interface MediaEvidenceRepository
{
    /** @return array{id:int,public_id:string,workspace_id:int,status:string,version:int,storage_key:string,byte_size:int,sha256:string}|null */
    public function lock(int $workspaceId, UuidV7 $assetId): ?array;
    /** @return array{id:int,public_id:string,status:string,version:int} */
    public function createStaged(int $workspaceId, int $actorAccountId, string $purpose, string $kind, string $safeFilename, string $storageKey, DateTimeImmutable $now): array;
    /** @param array{id:int,public_id:string,workspace_id:int,status:string,version:int,storage_key:string,byte_size:int,sha256:string} $asset */
    public function finalizeUpload(array $asset, string $mimeType, int $byteSize, string $sha256, DateTimeImmutable $now): bool;
    /** @param array{id:int,public_id:string,workspace_id:int,status:string,version:int,storage_key:string,byte_size:int,sha256:string} $asset */
    public function transition(array $asset, string $target, DateTimeImmutable $now): bool;
    public function appendEvent(int $workspaceId, int $assetId, string $eventCode, ?int $actorAccountId, DateTimeImmutable $now): void;

    /** @return array{state:'CLAIMED'|'REPLAY'|'CONFLICT',asset_id:?string,status:?string,version:?int} */
    public function claimUploadSubmission(int $workspaceId, int $actorAccountId, UuidV7 $submission, string $requestFingerprint, DateTimeImmutable $now): array;
    public function completeUploadSubmission(UuidV7 $submission, string $assetPublicId, string $status, int $version, DateTimeImmutable $now): void;
    public function enqueueScan(int $workspaceId, int $assetId, DateTimeImmutable $now): void;
    public function ensurePrivateDeliveryPolicy(int $workspaceId, int $assetId, DateTimeImmutable $now): void;
    /** @return array{storage_key:string,mime_type:string,byte_size:int,sha256:string}|null */
    public function findDeliverable(int $workspaceId, UuidV7 $assetId): ?array;
}
