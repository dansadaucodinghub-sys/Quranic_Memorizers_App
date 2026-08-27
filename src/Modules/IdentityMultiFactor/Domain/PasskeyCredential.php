<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Domain;

use DateTimeImmutable;

final readonly class PasskeyCredential
{
    /** @param list<string> $transports */
    public function __construct(
        public int $internalId,
        public string $publicId,
        public int $accountInternalId,
        private string $credentialId,
        private string $credentialPublicKey,
        public int $signatureCounter,
        public ?string $aaguid,
        public array $transports,
        public bool $backupEligible,
        public bool $backupState,
        public string $attestationFormat,
        public string $displayName,
        public PasskeyCredentialStatus $status,
        public int $version,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $lastUsedAt,
    ) {
        if (
            $credentialId === '' || strlen($credentialId) > 1024 || $credentialPublicKey === ''
            || $signatureCounter < 0 || $version < 1 || trim($displayName) === ''
            || mb_strlen($displayName) > 120
        ) {
            throw new \InvalidArgumentException('Passkey credential is inconsistent.');
        }
    }

    public function credentialIdForVerification(): string
    {
        return $this->credentialId;
    }

    public function publicKeyForVerification(): string
    {
        return $this->credentialPublicKey;
    }

    /** @return array{credential: string} */
    public function __debugInfo(): array
    {
        return ['credential' => '[REDACTED]'];
    }
}
