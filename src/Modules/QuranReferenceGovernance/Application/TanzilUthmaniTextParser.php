<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Application;

final readonly class TanzilUthmaniTextParser
{
    /** @return array{records:list<array{surah_number:int,ayah_number:int,text:string,byte_size:int,sha256:string}>,canonical_text_sha256:string} */
    public function parse(string $path, int $maximumBytes = 16777216, int $maximumLineBytes = 65536): array
    {
        if ($maximumBytes < 1 || $maximumLineBytes < 1 || !is_file($path) || filesize($path) === false || filesize($path) > $maximumBytes) {
            throw new \InvalidArgumentException('Canonical Tanzil artifact is unavailable or exceeds its limit.');
        }
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Canonical Tanzil artifact cannot be opened.');
        }
        $records = [];
        $seen = [];
        $lastSurah = 0;
        $lastAyah = 0;
        $content = hash_init('sha256');
        try {
            while (($line = fgets($handle, $maximumLineBytes + 1)) !== false) {
                if (strlen($line) > $maximumLineBytes || str_contains($line, "\0")) {
                    throw new \InvalidArgumentException('Canonical Tanzil record exceeds limits or contains NUL.');
                }
                $line = preg_replace('/\r?\n\z/', '', $line) ?? '';
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                if (!mb_check_encoding($line, 'UTF-8') || preg_match('/\A([1-9][0-9]*)\|([1-9][0-9]*)\|(.+)\z/u', $line, $match) !== 1) {
                    throw new \InvalidArgumentException('Canonical Tanzil record format is invalid.');
                }
                [, $surah, $ayah, $text] = $match;
                $surahNumber = (int) $surah;
                $ayahNumber = (int) $ayah;
                $identity = $surah . ':' . $ayah;
                if (isset($seen[$identity]) || $surahNumber < $lastSurah || ($surahNumber === $lastSurah && $ayahNumber !== $lastAyah + 1)) {
                    throw new \InvalidArgumentException('Canonical Tanzil identifiers are duplicated, missing, or out of order.');
                }
                $seen[$identity] = true;
                $lastSurah = $surahNumber;
                $lastAyah = $ayahNumber;
                $encoded = pack('N3', $surahNumber, $ayahNumber, strlen($text)) . $text;
                hash_update($content, $encoded);
                $records[] = ['surah_number' => $surahNumber, 'ayah_number' => $ayahNumber, 'text' => $text, 'byte_size' => strlen($text), 'sha256' => hash('sha256', $encoded)];
            }
        } finally {
            fclose($handle);
        }
        if ($records === []) {
            throw new \InvalidArgumentException('Canonical Tanzil artifact contains no records.');
        }

        return ['records' => $records, 'canonical_text_sha256' => hash_final($content)];
    }
}
