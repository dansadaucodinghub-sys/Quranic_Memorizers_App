<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaProcessing\Infrastructure\Process;

use Qmdb\Modules\MediaProcessing\Application\MediaProcessor;
use Qmdb\Modules\MediaProcessing\Application\MediaProcessorHealthCheck;

/** Fixed-profile FFmpeg adapter. Media bytes and browser input never become command arguments. */
final readonly class FfmpegMediaProcessor implements MediaProcessor, MediaProcessorHealthCheck
{
    public function __construct(private string $binary, private int $timeoutSeconds = 120) { if($binary===''||str_contains($binary,"\0")||$timeoutSeconds<1||$timeoutSeconds>600)throw new \InvalidArgumentException('FFmpeg configuration is invalid.'); }
    public function process(string $contents,array $profile): string
    {
        if(!is_file($this->binary)||$contents==='')throw new \RuntimeException('Trusted FFmpeg executable is unavailable.');
        $audio=($profile['profile']??'')==='AUDIO_NORMALIZED'; $input=tempnam(sys_get_temp_dir(),'qmdb-media-in-');$output=tempnam(sys_get_temp_dir(),'qmdb-media-out-');if($input===false||$output===false)throw new \RuntimeException('Media processing temporary storage is unavailable.');@unlink($output);$target=$output.($audio?'.mp3':'.mp4');
        try{if(file_put_contents($input,$contents,LOCK_EX)!==strlen($contents))throw new \RuntimeException('Media processing temporary storage is unavailable.');$command=$audio?[$this->binary,'-nostdin','-v','error','-i',$input,'-map','0:a:0','-vn','-c:a','libmp3lame','-b:a','128k','-f','mp3',$target]:[$this->binary,'-nostdin','-v','error','-i',$input,'-map','0:v:0','-map','0:a:0?','-c:v','libx264','-preset','medium','-crf','23','-c:a','aac','-b:a','128k','-movflags','+faststart','-f','mp4',$target];$this->run($command);$processed=file_get_contents($target);if(!is_string($processed)||$processed==='')throw new \RuntimeException('FFmpeg produced no media output.');return $processed;}finally{@unlink($input);@unlink($target);}
    }
    public function check(): array { if(!is_file($this->binary))return ['healthy'=>false,'engine'=>'FFMPEG','safe_code'=>'BINARY_UNAVAILABLE']; try{$this->run([$this->binary,'-version']);return ['healthy'=>true,'engine'=>'FFMPEG','safe_code'=>'READY'];}catch(\Throwable){return ['healthy'=>false,'engine'=>'FFMPEG','safe_code'=>'VERSION_FAILED'];} }
    /** @param list<string> $command */ private function run(array $command):void{$pipes=[];$process=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,null,['bypass_shell'=>true]);if(!is_resource($process))throw new \RuntimeException('FFmpeg could not start.');fclose($pipes[0]);stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);$deadline=microtime(true)+$this->timeoutSeconds;while(proc_get_status($process)['running']&&microtime(true)<$deadline){usleep(10_000);}if(proc_get_status($process)['running'])proc_terminate($process);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($process)!==0)throw new \RuntimeException('FFmpeg processing failed.');}
}
