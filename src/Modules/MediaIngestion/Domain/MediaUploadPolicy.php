<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaIngestion\Domain;

/** Server-authoritative upload limits and safe content families. */
final class MediaUploadPolicy
{
    public const MIN_CHUNK_BYTES = 262144;
    public const MAX_CHUNK_BYTES = 16777216;
    public const MAX_UPLOAD_BYTES = 10737418240;
    /** @return array{mime:string,extension:string} */
    public static function inspect(string $bytes): array
    {
        if ($bytes === '') throw new \InvalidArgumentException('Media content is invalid.');
        $finfo=new \finfo(FILEINFO_MIME_TYPE); $mime=$finfo->buffer($bytes); if (!is_string($mime)) throw new \InvalidArgumentException('Media MIME cannot be determined.');
        $map=['audio/mpeg'=>'mp3','audio/ogg'=>'ogg','audio/wav'=>'wav','video/mp4'=>'mp4','video/webm'=>'webm','image/jpeg'=>'jpg','image/png'=>'png'];
        if (!isset($map[$mime])) throw new \InvalidArgumentException('Unsupported media MIME type.');
        return ['mime'=>$mime,'extension'=>$map[$mime]];
    }
    public static function assertPart(int $start,int $end,int $size,int $expected): void { if ($start<0 || $end<$start || $size!==$end-$start+1 || $size<self::MIN_CHUNK_BYTES || $size>self::MAX_CHUNK_BYTES || $end>=$expected || $expected>self::MAX_UPLOAD_BYTES) throw new \InvalidArgumentException('Upload part range is invalid.'); }
}
