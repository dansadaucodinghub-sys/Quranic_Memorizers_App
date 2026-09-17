<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaProcessing\Infrastructure\Security;

use Qmdb\Modules\MediaProcessing\Application\MediaScanner;
use Qmdb\Modules\MediaProcessing\Application\MediaScannerHealthCheck;

/** Test-only scanner; it must never be bound by a production module. */
final class DeterministicMediaScanner implements MediaScanner, MediaScannerHealthCheck
{
    public function scan(string $contents): array
    {
        $infected = str_contains($contents, 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE');
        return ['clean' => !$infected, 'engine' => 'DETERMINISTIC_TEST', 'safe_code' => $infected ? 'INFECTED' : 'CLEAN'];
    }

    public function check(): array
    {
        return ['healthy' => true, 'engine' => 'DETERMINISTIC_TEST', 'safe_code' => 'TEST_ONLY'];
    }
}
