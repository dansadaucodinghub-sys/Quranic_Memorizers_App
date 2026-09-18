<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Media;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\MediaProcessing\Infrastructure\Process\{ConfiguredMediaRuntimeReadiness, FailClosedMediaProcessor};
use Qmdb\Modules\MediaProcessing\Infrastructure\Security\FailClosedMediaScanner;

final class MediaRuntimeReadinessTest extends TestCase
{
    public function testSuccessfulUnrelatedExecutableCannotImpersonateFfmpeg(): void
    {
        $processor = new \Qmdb\Modules\MediaProcessing\Infrastructure\Process\FfmpegMediaProcessor(PHP_BINARY);
        self::assertFalse($processor->check()['healthy']);
    }

    public function testExistingExecutablePathsDoNotSubstituteForConfiguredEngines(): void
    {
        $result = (new ConfiguredMediaRuntimeReadiness(new FailClosedMediaScanner(), new FailClosedMediaProcessor(), PHP_BINARY))->check();
        self::assertFalse($result['healthy']);
        self::assertSame('SCANNER_UNAVAILABLE', $result['checks']['scanner']);
        self::assertSame('BINARY_UNAVAILABLE', $result['checks']['processor']);
        self::assertSame('PROBE_UNAVAILABLE', $result['checks']['probe']);
    }
}
