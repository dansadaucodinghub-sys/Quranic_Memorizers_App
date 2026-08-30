<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Application;

/**
 * Deferred production integration boundary. Implementations must publish only completed checkpoints.
 */
interface SecurityAuditCheckpointPublisher
{
    public function publish(SecurityAuditCheckpointEnvelope $checkpoint): SecurityAuditCheckpointPublicationReceipt;
}
