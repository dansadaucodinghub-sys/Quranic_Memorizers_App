<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityPrivilegedAccess\Application;

use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessReviewId;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessReviewOutcome;
use Qmdb\Shared\Observability\Correlation\CorrelationId;

final readonly class PrivilegedAccessReviewCommand
{
    public function __construct(
        public AuthenticatedAccountContext $actor,
        public PrivilegedAccessReviewId $reviewId,
        public PrivilegedAccessReviewOutcome $outcome,
        string $summary,
        public CorrelationId $correlationId,
    ) {
        $summary = trim($summary);
        if ($summary === '' || !mb_check_encoding($summary, 'UTF-8') || str_contains($summary, "\0")) {
            throw new \InvalidArgumentException('Privileged-access review summary is invalid.');
        }
        $this->summary = $summary;
    }

    public string $summary;
}
