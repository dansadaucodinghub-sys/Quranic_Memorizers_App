<?php

declare(strict_types=1);

namespace Qmdb\Modules\Identity\Domain\Repository;

use DateTimeImmutable;
use Qmdb\Modules\Identity\Domain\AccountAuthenticationRecord;
use Qmdb\Modules\Identity\Domain\AccountContactStatus;
use Qmdb\Modules\Identity\Domain\AccountStatus;
use Qmdb\Modules\Identity\Domain\UserAccount;
use Qmdb\Modules\Identity\Domain\Value\AccountEmailId;
use Qmdb\Modules\Identity\Domain\Value\AccountPhoneId;
use Qmdb\Modules\Identity\Domain\Value\CredentialId;
use Qmdb\Modules\Identity\Domain\Value\LookupHash;
use Qmdb\Modules\Identity\Domain\Value\SensitivePasswordHash;

interface AccountRepository
{
    public function create(UserAccount $account): int;

    public function addEmail(
        int $accountInternalId,
        AccountEmailId $emailId,
        string $ciphertext,
        string $encryptionKeyId,
        LookupHash $lookupHash,
        AccountContactStatus $status,
        DateTimeImmutable $createdAt,
    ): int;

    public function addPhone(
        int $accountInternalId,
        AccountPhoneId $phoneId,
        string $ciphertext,
        string $encryptionKeyId,
        LookupHash $lookupHash,
        AccountContactStatus $status,
        DateTimeImmutable $createdAt,
    ): int;

    public function addPasswordCredential(
        int $accountInternalId,
        CredentialId $credentialId,
        SensitivePasswordHash $passwordHash,
        string $algorithm,
        int $metadataVersion,
        DateTimeImmutable $createdAt,
    ): int;

    public function authenticationByEmailHash(LookupHash $lookupHash): ?AccountAuthenticationRecord;

    public function activateVerifiedEmail(
        int $accountInternalId,
        int $emailInternalId,
        int $expectedAccountVersion,
        DateTimeImmutable $now,
    ): bool;

    public function replacePassword(
        int $accountInternalId,
        SensitivePasswordHash $passwordHash,
        string $algorithm,
        int $metadataVersion,
        int $expectedVersion,
        DateTimeImmutable $now,
    ): bool;
}
