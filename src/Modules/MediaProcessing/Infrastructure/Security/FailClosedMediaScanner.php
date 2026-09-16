<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaProcessing\Infrastructure\Security;

use Qmdb\Modules\MediaProcessing\Application\MediaScanner;

/** Safe default until a configured production scanner implementation is installed. */
final class FailClosedMediaScanner implements MediaScanner
{
    public function scan(string $contents): array
    {
        if ($contents === '') return ['clean'=>false,'engine'=>'FAIL_CLOSED','safe_code'=>'EMPTY_CONTENT'];
        if (str_contains($contents, 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE')) return ['clean'=>false,'engine'=>'FAIL_CLOSED','safe_code'=>'MALWARE_TEST_SIGNATURE'];
        return ['clean'=>false,'engine'=>'FAIL_CLOSED','safe_code'=>'SCANNER_NOT_CONFIGURED'];
    }
}
