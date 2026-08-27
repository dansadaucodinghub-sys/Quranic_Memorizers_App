<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Domain;

use DateTimeImmutable;

final readonly class WebAuthnCeremony
{
    public function __construct(
        public int $internalId,
        public string $publicId,
        public ?int $accountInternalId,
        public ?int $sessionInternalId,
        public ?int $authenticationTransactionInternalId,
        public WebAuthnCeremonyPurpose $purpose,
        private string $challengeHash,
        public WebAuthnCeremonyStatus $status,
        public int $attemptCount,
        public int $maximumAttempts,
        public DateTimeImmutable $expiresAt,
        public int $version,
    ) {
        if (
            strlen($challengeHash) !== 32 || $attemptCount < 0 || $maximumAttempts < 1
            || $attemptCount > $maximumAttempts || $version < 1
        ) {
            throw new \InvalidArgumentException('WebAuthn ceremony is inconsistent.');
        }
    }

    public function accepts(WebAuthnChallenge $challenge, DateTimeImmutable $now): bool
    {
        return $this->status === WebAuthnCeremonyStatus::PENDING
            && $now < $this->expiresAt
            && $this->attemptCount < $this->maximumAttempts
            && $challenge->matchesHash($this->challengeHash);
    }

    /** @return array{challenge_hash: string} */
    public function __debugInfo(): array
    {
        return ['challenge_hash' => '[REDACTED]'];
    }
}
