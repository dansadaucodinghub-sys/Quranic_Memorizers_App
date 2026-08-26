<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Job;

use DateTimeImmutable;
use Qmdb\Shared\Time\UtcDateTime;

final readonly class ReservedBackgroundJob
{
    private ?DateTimeImmutable $reservationExpiresAt;

    public function __construct(
        private BackgroundJobEnvelope $envelope,
        private JobReservationToken $reservationToken,
        ?DateTimeImmutable $reservationExpiresAt = null,
    ) {
        $this->reservationExpiresAt = $reservationExpiresAt === null
            ? null
            : UtcDateTime::normalize($reservationExpiresAt);
    }

    public function envelope(): BackgroundJobEnvelope
    {
        return $this->envelope;
    }

    public function reservationToken(): JobReservationToken
    {
        return $this->reservationToken;
    }

    public function reservationExpiresAt(): ?DateTimeImmutable
    {
        return $this->reservationExpiresAt;
    }

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return ['envelope' => $this->envelope->__debugInfo(), 'reservation_token' => '[REDACTED]'];
    }
}
