<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Domain\Repository;

use DateTimeImmutable;
use Qmdb\Modules\IdentitySessions\Domain\LoginSubmissionId;
use Qmdb\Modules\IdentitySessions\Domain\SessionId;
use Qmdb\Modules\IdentitySessions\Domain\SessionRevocationReason;
use Qmdb\Modules\IdentitySessions\Domain\SessionTokenHash;

interface UserSessionRepository
{
    public function findAuthenticationRecord(SessionId $sessionId): ?SessionAuthenticationRecord;

    public function loginSubmissionExists(LoginSubmissionId $submissionId): bool;

    public function lockAccount(int $accountInternalId): void;

    public function expireEffectiveSessions(int $accountInternalId, DateTimeImmutable $now): void;

    public function revokeOldestForLimit(int $accountInternalId, int $keepCount, DateTimeImmutable $now): void;

    public function createSession(
        SessionId $sessionId,
        int $accountInternalId,
        int $deviceInternalId,
        LoginSubmissionId $submissionId,
        SessionTokenHash $tokenHash,
        DateTimeImmutable $now,
        DateTimeImmutable $idleExpiresAt,
        DateTimeImmutable $absoluteExpiresAt,
    ): void;

    public function rotate(
        int $sessionInternalId,
        int $expectedVersion,
        SessionTokenHash $previousHash,
        SessionTokenHash $newHash,
        DateTimeImmutable $previousExpiresAt,
        DateTimeImmutable $now,
    ): bool;

    public function touchSession(
        int $sessionInternalId,
        int $expectedVersion,
        DateTimeImmutable $lastSeenAt,
        DateTimeImmutable $idleExpiresAt,
    ): bool;

    public function markExpired(int $sessionInternalId, int $expectedVersion, DateTimeImmutable $now): bool;

    public function revokeCurrent(
        int $accountInternalId,
        int $sessionInternalId,
        SessionRevocationReason $reason,
        DateTimeImmutable $now,
    ): bool;

    public function revokeOwned(
        int $accountInternalId,
        SessionId $sessionId,
        int $expectedVersion,
        SessionRevocationReason $reason,
        DateTimeImmutable $now,
    ): bool;

    public function revokeForDevice(
        int $accountInternalId,
        int $deviceInternalId,
        SessionRevocationReason $reason,
        DateTimeImmutable $now,
    ): int;

    public function revokeAllForAccount(
        int $accountInternalId,
        SessionRevocationReason $reason,
        DateTimeImmutable $now,
    ): int;

    /** @return list<SessionInventoryRecord> */
    public function listSessionsForAccount(int $accountInternalId, int $limit = 50): array;
}
