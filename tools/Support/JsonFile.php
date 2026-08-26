<?php

declare(strict_types=1);

namespace Qmdb\Tools\Support;

final class JsonFile
{
    /** @return array<string, mixed> */
    public static function readObject(string $path): array
    {
        $contents = file_get_contents($path);
        if (!is_string($contents)) {
            throw new \RuntimeException('Unable to read JSON file: ' . $path);
        }
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new \RuntimeException('Expected a JSON object: ' . $path);
        }
        $object = [];
        foreach ($decoded as $key => $value) {
            if (!is_string($key)) {
                throw new \RuntimeException('Expected string JSON object keys: ' . $path);
            }
            $object[$key] = $value;
        }
        return $object;
    }

    /** @param array<string, mixed> $value */
    public static function writeObject(string $path, array $value): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create directory: ' . $directory);
        }
        $json = json_encode(
            $value,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ) . "\n";
        $temporary = $path . '.tmp-' . bin2hex(random_bytes(6));
        if (file_put_contents($temporary, $json, LOCK_EX) === false || !rename($temporary, $path)) {
            @unlink($temporary);
            throw new \RuntimeException('Unable to write JSON file: ' . $path);
        }
    }
}
