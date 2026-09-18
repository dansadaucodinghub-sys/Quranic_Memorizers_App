<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaModeration\Application;

use DateTimeImmutable;
use Qmdb\Modules\MediaModeration\Domain\MediaGovernanceAction;
use Qmdb\Modules\MediaModeration\Domain\MediaGovernanceRecord;
use Qmdb\Shared\Identifier\UuidV7;

interface MediaGovernanceRepository
{
    public function find(int $workspace, UuidV7 $asset, bool $lock = false): ?MediaGovernanceRecord;
    /** @return list<MediaGovernanceRecord> */
    public function recent(int $workspace): array;
    public function grantConsent(MediaGovernanceRecord $asset, \Qmdb\Modules\MediaModeration\Domain\MediaConsentEvidence $evidence, int $actor, DateTimeImmutable $now): void;
    /** @return array{asset_id:string,status:string,version:int}|null */
    public function replay(UuidV7 $submission, string $fingerprint): ?array;
    public function apply(MediaGovernanceRecord $asset, MediaGovernanceAction $action, string $target, int $actor, string $holdCode, DateTimeImmutable $now): void;
    public function record(UuidV7 $submission, string $fingerprint, MediaGovernanceRecord $asset, MediaGovernanceAction $action, string $target, int $actor, string $reason, DateTimeImmutable $now): void;
}
