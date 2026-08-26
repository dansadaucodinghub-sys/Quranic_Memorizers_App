<?php

declare(strict_types=1);

namespace Qmdb\Modules\Identity\Domain;

use DateTimeImmutable;
use InvalidArgumentException;
use Qmdb\Modules\Identity\Domain\Value\AccountId;

final readonly class UserAccount
{
    public function __construct(
        public ?int $internalId,
        public AccountId $publicId,
        public AccountStatus $status,
        public string $preferredLocale,
        public string $preferredTimeZone,
        public int $version,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
        $localeIsSupported = in_array($preferredLocale, ['en', 'ar'], true);
        $timeZoneIsSupported = in_array($preferredTimeZone, timezone_identifiers_list(), true);
        if (!$localeIsSupported || !$timeZoneIsSupported) {
            throw new InvalidArgumentException('Account locale or time zone is invalid.');
        }
        if ($version < 1) {
            throw new InvalidArgumentException('Account version must be positive.');
        }
    }
}
