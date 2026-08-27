<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Domain;

use DateTimeImmutable;

final readonly class RecoveryCodeSet
{
    public function __construct(
        public int $internalId,
        public string $publicId,
        public int $accountInternalId,
        public RecoveryCodeSetStatus $status,
        public int $remainingCodes,
        public int $version,
        public DateTimeImmutable $generatedAt,
    ) {
        if ($remainingCodes < 0 || $version < 1) {
            throw new \InvalidArgumentException('Recovery-code set is inconsistent.');
        }
    }
}
