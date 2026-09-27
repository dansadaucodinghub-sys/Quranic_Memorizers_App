<?php

declare(strict_types=1);

namespace Qmdb\Modules\ProductionHardening\Domain;

final readonly class RetentionDispositionPolicy
{
    /** @return array{eligible:bool,reason:string} */
    public function assess(\DateTimeImmutable $createdAt, int $retentionDays, bool $activeHold, \DateTimeImmutable $now): array
    {
        if ($activeHold) {
            return ['eligible' => false, 'reason' => 'ACTIVE_DATA_HOLD'];
        }
        if ($retentionDays < 1) {
            return ['eligible' => false, 'reason' => 'INVALID_POLICY'];
        }
        return $createdAt->modify('+' . $retentionDays . ' days') <= $now
            ? ['eligible' => true, 'reason' => 'RETENTION_EXPIRED']
            : ['eligible' => false, 'reason' => 'RETENTION_ACTIVE'];
    }
}
