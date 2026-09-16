<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaIngestion\Infrastructure\Storage;

use Qmdb\Modules\MediaIngestion\Application\MediaBlobStore;

/** Development/test store outside public/ with immutable object writes. */
final readonly class LocalPrivateMediaBlobStore implements MediaBlobStore
{
    public function __construct(private string $root) { if ($root === '' || str_contains($root, "\0")) throw new \InvalidArgumentException('Media root is invalid.'); }
    public function putImmutable(string $objectKey, string $contents): void
    {
        if (!$this->valid($objectKey) || $contents === '') throw new \InvalidArgumentException('Media object is invalid.');
        $path=$this->path($objectKey); $directory=dirname($path);
        if (!is_dir($directory) && !mkdir($directory,0700,true) && !is_dir($directory)) throw new \RuntimeException('Media directory cannot be created.');
        if (is_file($path)) { $old=file_get_contents($path); if (!is_string($old) || !hash_equals(hash('sha256',$old,true),hash('sha256',$contents,true))) throw new \DomainException('Media object key is immutable.'); return; }
        $temporary=$path.'.tmp-'.bin2hex(random_bytes(8));
        if (file_put_contents($temporary,$contents,LOCK_EX)!==strlen($contents) || !rename($temporary,$path)) { @unlink($temporary); throw new \RuntimeException('Media object cannot be stored.'); }
        @chmod($path,0600);
    }
    public function get(string $objectKey): string { if (!$this->valid($objectKey)) throw new \InvalidArgumentException('Media object key is invalid.'); $contents=file_get_contents($this->path($objectKey)); if (!is_string($contents)) throw new \RuntimeException('Media object is unavailable.'); return $contents; }
    private function path(string $key): string { return rtrim($this->root,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$key); }
    private function valid(string $key): bool { return preg_match('~\A(?:staging|quarantine|private|variants)/[a-z0-9][a-z0-9._/-]{2,300}\z~',$key)===1 && !str_contains($key,'..'); }
}
