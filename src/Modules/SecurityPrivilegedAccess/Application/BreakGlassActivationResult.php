<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityPrivilegedAccess\Application;

use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessActivationId;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessRequestId;

final readonly class BreakGlassActivationResult
{
    public function __construct(
        public PrivilegedAccessRequestId $requestId,
        public PrivilegedAccessActivationId $activationId,
    ) {
    }
}
