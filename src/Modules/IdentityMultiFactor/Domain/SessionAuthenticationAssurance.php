<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Domain;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class SessionAuthenticationAssurance
{
    public function __construct(
        public AuthenticationMethod $primaryMethod,
        public ?AuthenticationMethod $secondaryMethod,
        public AuthenticationAssuranceLevel $level,
        public DateTimeImmutable $authenticatedAt,
        public ?DateTimeImmutable $strongAuthenticatedAt,
    ) {
        $valid = match ($level) {
            AuthenticationAssuranceLevel::PRIMARY => $secondaryMethod === null
                && $strongAuthenticatedAt === null,
            AuthenticationAssuranceLevel::MULTI_FACTOR => in_array(
                $secondaryMethod,
                [AuthenticationMethod::TOTP, AuthenticationMethod::RECOVERY_CODE],
                true,
            ) && $strongAuthenticatedAt !== null,
            AuthenticationAssuranceLevel::PHISHING_RESISTANT => (
                $primaryMethod === AuthenticationMethod::PASSKEY
                || $secondaryMethod === AuthenticationMethod::PASSKEY
            ) && $strongAuthenticatedAt !== null,
        };
        if (!$valid) {
            throw new InvalidArgumentException('Session authentication assurance is inconsistent.');
        }
    }
}
