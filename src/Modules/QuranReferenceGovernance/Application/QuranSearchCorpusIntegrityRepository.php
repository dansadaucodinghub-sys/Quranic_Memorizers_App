<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Application;

use DateTimeImmutable;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Shared\Identifier\UuidV7;

/** Read/write boundary for the private, immutable search-corpus integrity workflow. */
interface QuranSearchCorpusIntegrityRepository
{
    /** @return array<string, int|string>|null */
    public function findForRelease(UuidV7 $releasePublicId): ?array;

    /** @return array<string, int|string>|null */
    public function lockForValidation(UuidV7 $releasePublicId): ?array;

    /** @return list<array<string, int|string>> */
    public function alignedRows(int $corpusInternalId): array;

    /** @return array<string, int|string>|null */
    public function completed(UuidV7 $submissionId, string $fingerprint): ?array;

    public function appendValidation(int $corpusInternalId, int $accountInternalId, string $evidenceSha256, DateTimeImmutable $occurredAt): string;

    public function recordOperation(UuidV7 $submissionId, string $fingerprint, int $corpusInternalId, AuthenticatedAccountContext $actor, int $expectedVersion, string $validationPublicId, string $auditEventPublicId, int $stepUpGrantInternalId, DateTimeImmutable $occurredAt): void;
}
