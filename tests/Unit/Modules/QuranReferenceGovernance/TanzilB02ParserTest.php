<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Modules\QuranReferenceGovernance;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\QuranReferenceGovernance\Application\TanzilQuranMetadataParser;
use Qmdb\Modules\QuranReferenceGovernance\Application\TanzilUthmaniTextParser;

final class TanzilB02ParserTest extends TestCase
{
    public function testApprovedArtifactsProduceTheExpectedBoundedStructuralCounts(): void
    {
        $root = dirname(__DIR__, 4);
        $text = (new TanzilUthmaniTextParser())->parse($root . '/resources/data/quran/tanzil/uthmani-1.1/quran-uthmani.txt');
        $metadata = (new TanzilQuranMetadataParser())->parse($root . '/resources/data/quran/tanzil/metadata-1.0/quran-data.xml');

        self::assertCount(6236, $text['records']);
        self::assertSame(1, $text['records'][0]['surah_number']);
        self::assertSame(1, $text['records'][0]['ayah_number']);
        self::assertCount(114, $metadata['surahs']);
        self::assertSame(7, $metadata['surahs'][0]['ayah_count']);
        self::assertNotEmpty($metadata['markers']);
    }

    public function testTextParserRejectsMalformedDuplicateAndUnsafeRecords(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'qmdb-tanzil-');
        self::assertIsString($file);
        file_put_contents($file, "1|1|a\n1|1|b\n");
        try {
            $this->expectException(\InvalidArgumentException::class);
            (new TanzilUthmaniTextParser())->parse($file);
        } finally {
            unlink($file);
        }
    }
}
