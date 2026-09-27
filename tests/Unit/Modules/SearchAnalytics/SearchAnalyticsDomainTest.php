<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Modules\SearchAnalytics;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\SearchAnalytics\Domain\ArabicSearchNormalizer;
use Qmdb\Modules\SearchAnalytics\Domain\DeterministicExportFormatter;
use Qmdb\Modules\SearchAnalytics\Domain\PrivacyDisclosurePolicy;

final class SearchAnalyticsDomainTest extends TestCase
{
    public function testArabicNormalizationIsDeterministicAndRemovesDiacritics(): void
    {
        $normalizer = new ArabicSearchNormalizer();

        self::assertSame('احمد يقرا القران', $normalizer->normalize('  أَحْمَدُ يَقْرَأُ القُرْآنَ  '));
        self::assertSame('surah 2 result', $normalizer->normalize('Surah-2   RESULT'));
    }

    public function testSmallGroupsAreSuppressedBeforeDisclosure(): void
    {
        $policy = new PrivacyDisclosurePolicy();

        self::assertSame(
            ['value' => null, 'suppression_code' => 'SMALL_GROUP'],
            $policy->disclose(4, 'MINIMUM_CELL_SIZE', 5, 1, false),
        );
        self::assertSame(
            ['value' => 5, 'suppression_code' => null],
            $policy->disclose(5, 'MINIMUM_CELL_SIZE', 5, 1, false),
        );
        self::assertSame(
            ['value' => null, 'suppression_code' => 'PRIVATE_SCOPE'],
            $policy->disclose(100, 'PLATFORM_PRIVATE_ONLY', 1, 1, false),
        );
    }

    public function testExportsHaveStableColumnsAndNeutralizeSpreadsheetFormulae(): void
    {
        $formatter = new DeterministicExportFormatter();
        $rows = [['name' => '=SUM(1,1)', 'count' => 2], ['count' => 1, 'name' => 'Safe']];

        $csv = $formatter->csv($rows, ['name', 'count']);

        self::assertSame("name,count\n\"'=SUM(1,1)\",2\nSafe,1\n", $csv);
        self::assertSame(
            "[{\"name\":\"=SUM(1,1)\",\"count\":2},{\"name\":\"Safe\",\"count\":1}]\n",
            $formatter->json($rows, ['name', 'count']),
        );
    }
}
