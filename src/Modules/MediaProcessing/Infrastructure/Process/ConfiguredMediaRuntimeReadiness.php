<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaProcessing\Infrastructure\Process;

use Qmdb\Modules\MediaProcessing\Application\{MediaRuntimeReadiness, MediaScanner, MediaScannerHealthCheck, MediaProcessor, MediaProcessorHealthCheck};

/** Checks execution and scanner database freshness, not mere pathname existence. */
final readonly class ConfiguredMediaRuntimeReadiness implements MediaRuntimeReadiness
{
    public function __construct(private MediaScanner $scanner, private MediaProcessor $processor, private ?string $probeBinary)
    {
    }
    public function check(): array
    {
        $scan = $this->scanner instanceof MediaScannerHealthCheck ? $this->scanner->check() : ['healthy' => false, 'safe_code' => 'SCANNER_UNAVAILABLE'];
        $process = $this->processor instanceof MediaProcessorHealthCheck ? $this->processor->check() : ['healthy' => false, 'safe_code' => 'PROCESSOR_UNAVAILABLE'];
        $probe = false;
        try {
            if ($this->probeBinary !== null && is_file($this->probeBinary)) {
                $result = BoundedMediaProcess::run([$this->probeBinary, '-version'], 15);
                $probe = $result['exit'] === 0 && str_starts_with($result['stdout'], 'ffprobe version ');
            }
        } catch (\Throwable) {
            $probe = false;
        }
        return ['healthy' => $scan['healthy'] && $process['healthy'] && $probe, 'checks' => ['scanner' => $scan['safe_code'], 'processor' => $process['safe_code'], 'probe' => $probe ? 'READY' : 'PROBE_UNAVAILABLE']];
    }
}
