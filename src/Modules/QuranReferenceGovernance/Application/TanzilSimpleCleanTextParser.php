<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Application;

final readonly class TanzilSimpleCleanTextParser
{
    /** @return list<array{surah_number:int,ayah_number:int,text:string,byte_size:int,sha256:string}> */
    public function parse(string $path, int $maximumBytes = 16777216, int $maximumLineBytes = 65536): array
    {
        if ($maximumBytes < 1 || $maximumLineBytes < 1 || !is_file($path) || filesize($path) === false || filesize($path) > $maximumBytes) {
            throw new \InvalidArgumentException('Simple Clean Tanzil artifact is unavailable or exceeds its limit.');
        }
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Simple Clean Tanzil artifact cannot be opened.');
        }
        $records = [];
        $seen = [];
        $previousSurah = 0;
        $previousAyah = 0;
        try {
            while (($line = fgets($handle, $maximumLineBytes + 1)) !== false) {
                if (strlen($line) > $maximumLineBytes || str_contains($line, "\0")) {
                    throw new \InvalidArgumentException('Simple Clean record exceeds limits or contains NUL.');
                }
                $line = preg_replace('/\r?\n\z/', '', $line) ?? '';
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                if (!mb_check_encoding($line, 'UTF-8') || preg_match('/\A([1-9][0-9]*)\|([1-9][0-9]*)\|(.+)\z/u', $line, $match) !== 1) {
                    throw new \InvalidArgumentException('Simple Clean record format is invalid.');
                }
                [, $surah, $ayah, $text] = $match;
                $s = (int)$surah;
                $a = (int)$ayah;
                $key = $surah . ':' . $ayah;
                if (isset($seen[$key]) || $s < $previousSurah || ($s === $previousSurah && $a !== $previousAyah + 1)) {
                    throw new \InvalidArgumentException('Simple Clean identifiers are duplicated, missing, or out of order.');
                }
                $seen[$key] = true;
                $previousSurah = $s;
                $previousAyah = $a;
                $hash = QuranSearchHasher::row($s, $a, $text);
                $records[] = ['surah_number' => $s, 'ayah_number' => $a, 'text' => $text, 'byte_size' => strlen($text), 'sha256' => $hash];
            }
        } finally {
            fclose($handle);
        }
        if ($records === []) {
            throw new \InvalidArgumentException('Simple Clean artifact contains no records.');
        }
        return $records;
    }
}
