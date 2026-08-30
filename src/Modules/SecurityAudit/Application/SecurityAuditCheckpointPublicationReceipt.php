<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Application;

use DateTimeImmutable;

/**
 * Safe external-publication acknowledgement. A provider reference must never contain secret credentials.
 */
final readonly class SecurityAuditCheckpointPublicationReceipt
{
    public function __construct(public string $providerReference, public DateTimeImmutable $publishedAt)
    {
    }
}
