<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityPrivilegedAccess\Application;

use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessReviewId;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessReviewOutcome;

final readonly class PrivilegedAccessReviewResult
{
    public function __construct(
        public PrivilegedAccessReviewId $reviewId,
        public PrivilegedAccessReviewOutcome $outcome,
    ) {
    }
}
