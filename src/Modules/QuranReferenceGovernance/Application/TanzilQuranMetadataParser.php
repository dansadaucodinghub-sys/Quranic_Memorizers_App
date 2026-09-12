<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Application;

use XMLReader;

final readonly class TanzilQuranMetadataParser
{
    /** @return array{surahs:list<array{surah_number:int,ayah_count:int,arabic_name:string,transliterated_name:string,english_name:string,revelation_type:string,revelation_order:int,ruku_count:int}>,markers:list<array{type:string,index:int,surah_number:int,ayah_number:int,sajdah_type:?string}>} */
    public function parse(string $path, int $maximumBytes = 4194304, int $maximumElements = 100000): array
    {
        if (!is_file($path) || filesize($path) === false || filesize($path) > $maximumBytes) {
            throw new \InvalidArgumentException('Tanzil metadata artifact is unavailable or exceeds its limit.');
        }
        $xml = new XMLReader();
        if (!$xml->open($path, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new \InvalidArgumentException('Tanzil metadata XML cannot be opened.');
        }
        $xml->setParserProperty(XMLReader::LOADDTD, false);
        $xml->setParserProperty(XMLReader::SUBST_ENTITIES, false);
        $surahs = [];
        $markers = [];
        $elements = 0;
        try {
            while ($xml->read()) {
                if (++$elements > $maximumElements) {
                    throw new \InvalidArgumentException('Tanzil metadata has too many XML elements.');
                }
                if ($xml->nodeType === XMLReader::DOC_TYPE) {
                    throw new \InvalidArgumentException('Tanzil metadata must not contain a DOCTYPE.');
                }
                if ($xml->nodeType !== XMLReader::ELEMENT) {
                    continue;
                }
                $name = $xml->localName;
                if ($name === 'sura') {
                    $surahs[] = $this->surah($xml);
                    continue;
                }
                $type = match ($name) {
                    'juz' => 'JUZ', 'hizb' => 'HIZB', 'quarter' => 'HIZB_QUARTER', 'manzil' => 'MANZIL',
                    'ruku' => 'RUKU', 'page' => 'MUSHAF_PAGE', 'sajda' => 'SAJDAH', default => null,
                };
                if ($type !== null) {
                    $markers[] = $this->marker($xml, $type);
                }
            }
        } finally {
            $xml->close();
        }
        if (count($surahs) !== 114) {
            throw new \InvalidArgumentException('Tanzil metadata did not provide the expected Surah definitions.');
        }

        return ['surahs' => $surahs, 'markers' => $markers];
    }

    /** @return array{surah_number:int,ayah_count:int,arabic_name:string,transliterated_name:string,english_name:string,revelation_type:string,revelation_order:int,ruku_count:int} */
    private function surah(XMLReader $xml): array
    {
        return ['surah_number' => $this->positive($xml, 'index'), 'ayah_count' => $this->positive($xml, 'ayas'), 'arabic_name' => $this->required($xml, 'name'), 'transliterated_name' => $this->required($xml, 'tname'), 'english_name' => $this->required($xml, 'ename'), 'revelation_type' => $this->required($xml, 'type'), 'revelation_order' => $this->positive($xml, 'order'), 'ruku_count' => $this->positive($xml, 'rukus')];
    }

    /** @return array{type:string,index:int,surah_number:int,ayah_number:int,sajdah_type:?string} */
    private function marker(XMLReader $xml, string $type): array
    {
        return ['type' => $type, 'index' => $this->positive($xml, 'index'), 'surah_number' => $this->positive($xml, 'sura'), 'ayah_number' => $this->positive($xml, 'aya'), 'sajdah_type' => $type === 'SAJDAH' ? $xml->getAttribute('type') : null];
    }

    private function positive(XMLReader $xml, string $name): int
    {
        $value = $this->required($xml, $name);
        if (preg_match('/\A[1-9][0-9]*\z/', $value) !== 1) {
            throw new \InvalidArgumentException('Tanzil metadata identifier is invalid.');
        }

        return (int) $value;
    }

    private function required(XMLReader $xml, string $name): string
    {
        $value = $xml->getAttribute($name);
        if (!is_string($value) || $value === '' || strlen($value) > 4096 || !mb_check_encoding($value, 'UTF-8')) {
            throw new \InvalidArgumentException('Tanzil metadata attribute is invalid.');
        }

        return $value;
    }
}
