<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaProcessing\Infrastructure\Process;

use Qmdb\Modules\MediaProcessing\Application\MediaProcessor;
use Qmdb\Modules\MediaProcessing\Application\MediaProcessorHealthCheck;

/** Fixed-profile FFmpeg adapter. Media bytes and browser input never become command arguments. */
final readonly class FfmpegMediaProcessor implements MediaProcessor, MediaProcessorHealthCheck
{
    public function __construct(private string $binary, private int $timeoutSeconds = 120)
    {
        if ($binary === '' || str_contains($binary, "\0") || $timeoutSeconds < 1 || $timeoutSeconds > 600) {
            throw new \InvalidArgumentException('FFmpeg configuration is invalid.');
        }
    }
    public function process(string $contents, array $profile): string
    {
        if (!is_file($this->binary) || $contents === '') {
            throw new \RuntimeException('Trusted FFmpeg executable is unavailable.');
        }
        if (!in_array($profile['profile'], ['AUDIO_NORMALIZED','VIDEO_NORMALIZED'], true)) {
            throw new \InvalidArgumentException('Unsupported media profile.');
        }
        $audio = $profile['profile'] === 'AUDIO_NORMALIZED';
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'qmdb-process-' . bin2hex(random_bytes(16));
        if (!mkdir($directory, 0700)) {
            throw new \RuntimeException('Media processing temporary storage is unavailable.');
        }
        $input = $directory . '/input.bin';
        $target = $directory . ($audio ? '/output.mp3' : '/output.mp4');
        try {
            if (file_put_contents($input, $contents, LOCK_EX) !== strlen($contents)) {
                throw new \RuntimeException('Media processing temporary storage is unavailable.');
            }
            $command = [$this->binary,'-nostdin','-v','error','-protocol_whitelist','file,pipe','-format_whitelist','mp3,wav,mov,matroska,webm,ogg,aac','-i',$input,'-map_metadata','-1','-map_chapters','-1','-threads','2'];
            $command = [...$command, ...($audio ? ['-map','0:a:0','-vn','-c:a','libmp3lame','-b:a','128k','-f','mp3'] : ['-map','0:v:0','-map','0:a:0?','-c:v','libx264','-preset','medium','-crf','23','-c:a','aac','-b:a','128k','-movflags','+faststart','-f','mp4']), '-fs','16777216',$target];
            $this->run($command);
            $size = filesize($target);
            if ($size === false || $size < 1 || $size >= 16_777_216) {
                throw new \RuntimeException('Processed media exceeded its bounded size.');
            }
            $processed = file_get_contents($target);
            if (!is_string($processed) || $processed === '') {
                throw new \RuntimeException('FFmpeg produced no media output.');
            }
            return $processed;
        } finally {
            if (is_file($input)) {
                unlink($input);
            }
            if (is_file($target)) {
                unlink($target);
            }
            rmdir($directory);
        }
    }
    public function check(): array
    {
        if (!is_file($this->binary)) {
            return ['healthy' => false,'engine' => 'FFMPEG','safe_code' => 'BINARY_UNAVAILABLE'];
        } try {
            $result = BoundedMediaProcess::run([$this->binary, '-version'], $this->timeoutSeconds);
            if ($result['exit'] !== 0 || preg_match('/\Affmpeg version \S+ /', $result['stdout']) !== 1) {
                return ['healthy' => false, 'engine' => 'FFMPEG', 'safe_code' => 'VERSION_FAILED'];
            }
            return ['healthy' => true,'engine' => 'FFMPEG','safe_code' => 'READY'];
        } catch (\Throwable) {
                return ['healthy' => false,'engine' => 'FFMPEG','safe_code' => 'VERSION_FAILED'];
        }
    }
    /** @param non-empty-list<string> $command */
    private function run(array $command): void
    {
        if (BoundedMediaProcess::run($command, $this->timeoutSeconds)['exit'] !== 0) {
            throw new \RuntimeException('FFmpeg processing failed.');
        }
    }
}
