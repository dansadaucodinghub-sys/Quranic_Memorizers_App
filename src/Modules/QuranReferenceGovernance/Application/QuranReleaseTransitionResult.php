<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Application;

final readonly class QuranReleaseTransitionResult
{
    public function __construct(public string $releasePublicId, public string $status, public int $version, public bool $replayed)
    {
    }
}
