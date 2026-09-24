<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Domain;

use Qmdb\Shared\Identifier\UuidV7;

final readonly class RecitationClip
{
    public function __construct(
        public int $internalId,
        public UuidV7 $publicId,
        public int $workspaceId,
        public int $creatorAccountId,
        public int $creatorPersonId,
        public int $mediaAssetId,
        public ClipStatus $status,
        public string $audience,
        public int $version,
    ) {
        if (
            $internalId < 1 || $workspaceId < 1 || $creatorAccountId < 1 || $creatorPersonId < 1
            || $mediaAssetId < 1 || $version < 1
        ) {
            throw new \InvalidArgumentException('Recitation Clip identity or version is invalid.');
        }
    }
}
