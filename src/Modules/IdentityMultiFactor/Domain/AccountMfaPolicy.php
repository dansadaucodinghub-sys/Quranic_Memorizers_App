<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Domain;

use DateTimeImmutable;

final readonly class AccountMfaPolicy
{
    public function __construct(
        public int $accountInternalId,
        public AccountMfaPolicyStatus $status,
        public ?AccountMfaPreferredMethod $preferredMethod,
        public int $version,
        public ?DateTimeImmutable $enabledAt,
        public ?DateTimeImmutable $disabledAt,
    ) {
        if ($version < 1 || $status === AccountMfaPolicyStatus::ENABLED && $enabledAt === null) {
            throw new \InvalidArgumentException('Account MFA policy is inconsistent.');
        }
    }

    public static function disabled(int $accountInternalId): self
    {
        return new self($accountInternalId, AccountMfaPolicyStatus::DISABLED, null, 1, null, null);
    }

    public function enabled(): bool
    {
        return $this->status === AccountMfaPolicyStatus::ENABLED;
    }
}
