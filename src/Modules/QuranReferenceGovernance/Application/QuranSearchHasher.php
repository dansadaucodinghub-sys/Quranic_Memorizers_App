<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Application;

final class QuranSearchHasher
{
    public static function row(int $surah, int $ayah, string $text): string
    {
        return hash('sha256', pack('N3', $surah, $ayah, strlen($text)) . $text);
    }
    /** @param list<array{surah_number:int,ayah_number:int,text:string,byte_size:int,sha256:string}> $records */
    public static function corpus(array $records): string
    {
        $hash = hash_init('sha256');
        foreach ($records as $record) {
            hash_update($hash, self::binaryChecksum($record['sha256']));
        }
        return hash_final($hash);
    }
    /**
     * @param list<array{public_id:string,surah_number:int,ayah_number:int,global_ayah_ordinal:int,text_sha256:string}> $ayahs
     * @param list<array{surah_number:int,ayah_number:int,text:string,byte_size:int,sha256:string}> $records
     */
    public static function alignment(string $releasePublicId, string $corpusPublicId, array $ayahs, array $records): string
    {
        $hash = hash_init('sha256');
        foreach ($ayahs as $index => $ayah) {
            $record = $records[$index] ?? null;
            if ($record === null || $record['surah_number'] !== $ayah['surah_number'] || $record['ayah_number'] !== $ayah['ayah_number']) {
                throw new \InvalidArgumentException('Search corpus does not align with canonical Ayat.');
            }
            hash_update($hash, pack('N3', $ayah['surah_number'], $ayah['ayah_number'], $ayah['global_ayah_ordinal']) . self::binaryChecksum(str_replace('-', '', $releasePublicId)) . self::binaryChecksum(str_replace('-', '', $corpusPublicId)) . self::binaryChecksum($ayah['text_sha256']) . self::binaryChecksum($record['sha256']));
        }
        return hash_final($hash);
    }

    private static function binaryChecksum(string $value): string
    {
        $binary = hex2bin($value);
        if ($binary === false) {
            throw new \InvalidArgumentException('Qur’an checksum is not valid hexadecimal.');
        }
        return $binary;
    }
}
