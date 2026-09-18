<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaProcessing\Infrastructure\Process;

use Qmdb\Modules\MediaProcessing\Application\MediaProcessor;
use Qmdb\Modules\MediaProcessing\Application\MediaProcessorHealthCheck;

final class FailClosedMediaProcessor implements MediaProcessor, MediaProcessorHealthCheck
{
    public function process(string $contents, array $profile): string
    {
        throw new \RuntimeException('Trusted FFmpeg processing is not configured.');
    }
    public function check(): array
    {
        return ['healthy' => false,'engine' => 'FFMPEG','safe_code' => 'BINARY_UNAVAILABLE'];
    }
}
