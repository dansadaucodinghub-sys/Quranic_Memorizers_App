<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Application;

use DateTimeImmutable;
use Qmdb\Shared\Identifier\UuidV7;

interface QuranReleaseLifecycleRepository
{
    /** @return array{id:int,public_id:string,status:string,version:int,release_code:string,release_version:string}|null */
    public function lockRelease(UuidV7 $publicId): ?array;

    public function activeReleaseExists(): bool;

    public function transition(array $release, string $status, int $actorAccountId, DateTimeImmutable $now): bool;

    public function appendEvent(array $release, string $eventType, string $toStatus, int $actorAccountId, ?string $reasonCode, string $correlationId, DateTimeImmutable $now): void;

    public function findCompleted(UuidV7 $submissionId, string $fingerprint): ?QuranReleaseTransitionResult;

    public function record(UuidV7 $submissionId, string $fingerprint, array $release, QuranReleaseTransitionCommand $command, ?int $stepUpGrantId, string $auditEventPublicId, DateTimeImmutable $now): void;
}
