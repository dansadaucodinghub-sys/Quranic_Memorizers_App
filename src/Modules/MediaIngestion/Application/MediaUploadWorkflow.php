<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaIngestion\Application;

use DateTimeImmutable;
use Qmdb\Modules\MediaCatalog\Application\MediaEvidenceRepository;
use Qmdb\Modules\MediaCatalog\Domain\MediaLifecycle;
use Qmdb\Modules\MediaIngestion\Domain\MediaUploadPolicy;
use Qmdb\Shared\Identifier\UuidV7;

/**
 * Server-authoritative staging/finalization workflow. HTTP authorization, CSRF,
 * idempotency, and rate limits must be enforced by the caller before this boundary.
 */
final readonly class MediaUploadWorkflow
{
    public function __construct(private MediaEvidenceRepository $assets, private MediaBlobStore $storage, private MediaLifecycle $lifecycle)
    {
    }

    /** @return array{id:int,public_id:string,status:string,version:int} */
    public function initiate(int $workspaceId, int $actorAccountId, string $purpose, string $mediaKind, string $displayFilename, string $opaqueStorageKey, DateTimeImmutable $now): array
    {
        $this->assertIdentity($workspaceId, $actorAccountId);
        if (preg_match('/\A[A-Z][A-Z0-9_]{1,47}\z/', $purpose) !== 1 || !in_array($mediaKind, ['AUDIO','VIDEO','IMAGE'], true)) {
            throw new \InvalidArgumentException('Media upload purpose or kind is invalid.');
        }
        $filename = $this->safeFilename($displayFilename);
        $asset = $this->assets->createStaged($workspaceId, $actorAccountId, $purpose, $mediaKind, $filename, $opaqueStorageKey, $now);
        $this->assets->appendEvent($workspaceId, $asset['id'], 'media.upload.initiated', $actorAccountId, $now);
        return $asset;
    }

    /** @return array{asset_id:string,status:string,version:int,mime:string,sha256:string} */
    public function finalize(int $workspaceId, int $actorAccountId, UuidV7 $assetId, int $expectedVersion, string $contents, DateTimeImmutable $now): array
    {
        $this->assertIdentity($workspaceId, $actorAccountId);
        if ($expectedVersion < 1 || $contents === '' || strlen($contents) > MediaUploadPolicy::MAX_UPLOAD_BYTES) {
            throw new \InvalidArgumentException('Media upload finalization input is invalid.');
        }
        $inspection = MediaUploadPolicy::inspect($contents);
        $asset = $this->assets->lock($workspaceId, $assetId);
        if ($asset === null || $asset['version'] !== $expectedVersion) {
            throw new \DomainException('Media upload is stale or unavailable.');
        }
        if ($asset['status'] === 'QUARANTINED' && $asset['sha256'] !== '' && hash_equals($asset['sha256'], hash('sha256', $contents, true))) {
            return ['asset_id' => $asset['public_id'],'status' => 'QUARANTINED','version' => $asset['version'],'mime' => $inspection['mime'],'sha256' => hash('sha256', $contents)];
        }
        $this->lifecycle->assertTransition($asset['status'], 'QUARANTINED');
        $this->storage->putImmutable($asset['storage_key'], $contents);
        $hash = hash('sha256', $contents, true);
        if (!$this->assets->finalizeUpload($asset, $inspection['mime'], strlen($contents), $hash, $now)) {
            throw new \DomainException('Media upload changed concurrently.');
        }
        $this->assets->appendEvent($workspaceId, $asset['id'], 'media.upload.completed', $actorAccountId, $now);
        return ['asset_id' => $asset['public_id'],'status' => 'QUARANTINED','version' => $asset['version'] + 1,'mime' => $inspection['mime'],'sha256' => bin2hex($hash)];
    }

    private function assertIdentity(int $workspaceId, int $actorAccountId): void
    {
        if ($workspaceId < 1 || $actorAccountId < 1) {
            throw new \InvalidArgumentException('Media upload actor context is invalid.');
        }
    }
    private function safeFilename(string $filename): string
    {
        if ($filename === '' || strlen($filename) > 180 || str_contains($filename, "\0") || !preg_match('//u', $filename)) {
            throw new \InvalidArgumentException('Media filename is invalid.');
        }
        $safe = trim(preg_replace('/[\\\\\/\x00-\x1F\x7F]+/u', '_', $filename) ?? '');
        if ($safe === '' || $safe === '.' || $safe === '..') {
            throw new \InvalidArgumentException('Media filename is invalid.');
        }
        return $safe;
    }
}
