<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Application;

use DateTimeImmutable;
use Qmdb\Modules\IdentityMultiFactor\Domain\TotpSecret;

final readonly class TotpEnrollmentResult
{
    public function __construct(
        public string $authenticatorPublicId,
        private TotpSecret $secret,
        public string $provisioningUri,
        public DateTimeImmutable $expiresAt,
    ) {
    }

    public function manualSecret(): string
    {
        return $this->secret->revealForEnrollment();
    }

    /** @return array{secret: string} */
    public function __debugInfo(): array
    {
        return ['secret' => '[REDACTED]'];
    }
}
