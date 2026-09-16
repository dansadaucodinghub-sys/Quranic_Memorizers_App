<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaProcessing\Infrastructure\Probe;

use Qmdb\Modules\MediaProcessing\Application\MediaProbe;

/** Refuses processing rather than trusting client-supplied probe metadata. */
final class UnavailableMediaProbe implements MediaProbe
{
    public function inspect(string $privateObjectKey): array
    {
        throw new \RuntimeException('Trusted media probe is not configured.');
    }
}
