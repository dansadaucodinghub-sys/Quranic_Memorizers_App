<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Application;

use Qmdb\Shared\Identifier\UuidV7;

interface CommunityPublicClipReader
{
    /** Internal result; only CommunityPublicClipService may expose an allowlisted projection.
     * @return array{clip_id:string,profile_id:string,alias:string,caption:string,language:string,surah:int,start:int,end:int,published_at:string,media_kind:string,comment_policy:string,workspace_id:int,asset_id:UuidV7}|null
     */
    public function find(UuidV7 $clipId, ?int $viewerAccountId): ?array;
}
