<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Domain\Repository;

use DateTimeImmutable;
use Qmdb\Modules\IdentityMultiFactor\Domain\PasskeyCredential;

interface PasskeyCredentialRepository
{
    /** @return list<PasskeyCredential> */
    public function listPasskeys(int $accountInternalId, bool $includeInactive = false): array;

    public function findPasskeyByCredentialId(string $credentialId, bool $forUpdate = false): ?PasskeyCredential;

    public function findPasskey(
        int $accountInternalId,
        string $publicId,
        bool $forUpdate = false,
    ): ?PasskeyCredential;

    /** @param list<string> $transports */
    public function createPasskey(
        int $accountInternalId,
        string $publicId,
        string $credentialId,
        string $credentialPublicKey,
        int $signatureCounter,
        ?string $aaguid,
        array $transports,
        bool $backupEligible,
        bool $backupState,
        string $attestationFormat,
        string $displayName,
        DateTimeImmutable $now,
    ): PasskeyCredential;

    public function recordPasskeyUse(
        PasskeyCredential $credential,
        int $newCounter,
        bool $backupState,
        DateTimeImmutable $now,
    ): bool;

    public function suspendPasskey(PasskeyCredential $credential, DateTimeImmutable $now): bool;

    public function revokePasskey(PasskeyCredential $credential, DateTimeImmutable $now): bool;
}
