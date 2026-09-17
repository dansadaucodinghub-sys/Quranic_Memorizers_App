<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaProcessing\Infrastructure\Probe;

use Qmdb\Modules\MediaIngestion\Application\MediaBlobStore;
use Qmdb\Modules\MediaProcessing\Application\MediaProbe;

/** FFprobe adapter with fixed arguments, bounded output, and no user-controlled command fragments. */
final readonly class FfprobeMediaProbe implements MediaProbe
{
    public function __construct(private MediaBlobStore $storage, private string $binary, private int $timeoutSeconds = 30)
    {
        if ($binary === '' || str_contains($binary, "\0") || $timeoutSeconds < 1 || $timeoutSeconds > 300) throw new \InvalidArgumentException('FFprobe configuration is invalid.');
    }
    public function inspect(string $privateObjectKey): array
    {
        $contents = $this->storage->get($privateObjectKey);
        $file = tempnam(sys_get_temp_dir(), 'qmdb-media-probe-');
        if ($file === false || file_put_contents($file, $contents, LOCK_EX) !== strlen($contents)) throw new \RuntimeException('Media probe temporary storage is unavailable.');
        try {
            $output = $this->run([$this->binary, '-v', 'error', '-show_entries', 'format=duration,format_name:stream=codec_name,codec_type,width,height', '-of', 'json', '--', $file]);
            $decoded = json_decode($output, true, 16, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) throw new \RuntimeException('Media probe returned invalid metadata.');
            $format = is_array($decoded['format'] ?? null) ? $decoded['format'] : [];
            $streams = is_array($decoded['streams'] ?? null) ? $decoded['streams'] : [];
            $audio = null; $video = null;
            foreach ($streams as $stream) if (is_array($stream)) { if (($stream['codec_type'] ?? null) === 'audio' && $audio === null) $audio = $stream; if (($stream['codec_type'] ?? null) === 'video' && $video === null) $video = $stream; }
            $duration = is_numeric($format['duration'] ?? null) ? (int) round((float) $format['duration'] * 1000) : 0;
            $selected = is_array($video) ? $video : (is_array($audio) ? $audio : []);
            $codec = is_string($selected['codec_name'] ?? null) ? $selected['codec_name'] : 'UNKNOWN';
            return ['mime' => 'application/octet-stream', 'duration_ms' => max(0, $duration), 'width' => is_numeric($video['width'] ?? null) ? (int) $video['width'] : null, 'height' => is_numeric($video['height'] ?? null) ? (int) $video['height'] : null, 'codec' => $codec];
        } finally { @unlink($file); }
    }
    /** @param list<string> $command */
    private function run(array $command): string
    {
        if (!is_file($this->binary)) throw new \RuntimeException('Trusted FFprobe executable is unavailable.');
        $pipes = []; $process = proc_open($command, [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, null, null, ['bypass_shell'=>true]);
        if (!is_resource($process)) throw new \RuntimeException('Media probe could not start.');
        fclose($pipes[0]); stream_set_blocking($pipes[1], false); stream_set_blocking($pipes[2], false); $output = ''; $deadline = microtime(true) + $this->timeoutSeconds;
        while (proc_get_status($process)['running'] && microtime(true) < $deadline) { $output .= stream_get_contents($pipes[1]) ?: ''; if (strlen($output) > 1_048_576) { proc_terminate($process); break; } usleep(10_000); }
        $output .= stream_get_contents($pipes[1]) ?: ''; fclose($pipes[1]); fclose($pipes[2]); $exit = proc_close($process);
        if ($exit !== 0 || strlen($output) > 1_048_576) throw new \RuntimeException('Trusted media probe failed.');
        return $output;
    }
}
