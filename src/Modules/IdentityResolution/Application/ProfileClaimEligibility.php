<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Application;

use DateTimeImmutable;
use DateTimeZone;

final readonly class ProfileClaimEligibility
{
    public static function adult(string $birthDate, int $thresholdYears, DateTimeImmutable $now): bool
    {
        if ($birthDate === '' || $thresholdYears < 1) {
            return false;
        }
        try {
            $birth = new DateTimeImmutable($birthDate, new DateTimeZone('UTC'));
        } catch (\Throwable) {
            return false;
        }

        return $birth->modify('+' . $thresholdYears . ' years') <= $now;
    }
}
