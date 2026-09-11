<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Application;

interface QuranPublicReferenceRepository
{
    /** @return array{release_code:string,release_version:string,surah_count:int,ayah_count:int}|null */
    public function home(): ?array;
    /** @return list<array<string, int|string>> */ public function surahs(): array;
    /** @return array{surah:array<string,int|string>,ayahs:list<array<string,int|string>>}|null */ public function surah(int $number): ?array;
    /** @return array<string,int|string>|null */ public function ayah(int $surah, int $ayah): ?array;
    /** @return list<array<string,int|string>> */ public function partitions(string $type, int $number): array;
    /** @return list<array<string,int|string>> */ public function sajdahs(): array;
    /** @return list<array<string,int|string>> */ public function search(string $mode, string $query, int $limit): array;
}
