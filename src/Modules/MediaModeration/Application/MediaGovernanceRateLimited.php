<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaModeration\Application;

final class MediaGovernanceRateLimited extends \DomainException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct('Media governance is temporarily rate limited.');
    }
}
