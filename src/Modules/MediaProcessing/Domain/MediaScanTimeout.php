<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaProcessing\Domain;

/** The scanner deadline leaves headroom inside the five-minute fenced job lease. */
final readonly class MediaScanTimeout
{
    public int $seconds;

    public function __construct(?string $configured)
    {
        $configured ??= '30';
        if (preg_match('/\A[1-9][0-9]{0,2}\z/', $configured) !== 1 || (int) $configured > 240) {
            throw new \InvalidArgumentException('QMDB_MEDIA_SCAN_TIMEOUT_SECONDS must be between 1 and 240.');
        }
        $this->seconds = (int) $configured;
    }
}
