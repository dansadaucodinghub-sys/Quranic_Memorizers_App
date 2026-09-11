<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Modules\QuranReferenceGovernance;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\QuranReferenceGovernance\Application\QuranSearchHasher;
use Qmdb\Modules\QuranReferenceGovernance\Application\QuranSearchQueryNormalizer;

final class QuranSearchQueryNormalizerTest extends TestCase
{
    public function testSimpleModeNormalizesOnlyTheQuery(): void
    {
        self::assertSame('الحمد لله', (new QuranSearchQueryNormalizer())->normalize('SIMPLE', " الـحَمْدُ   لله "));
    }
    public function testExactModePreservesSourceSignificantMarks(): void
    {
        self::assertSame('ٱلْحَمْدُ', (new QuranSearchQueryNormalizer())->normalize('EXACT_UTHMANI', 'ٱلْحَمْدُ'));
    }
    public function testRejectsControlAndBidiInput(): void
    {
        $normalizer = new QuranSearchQueryNormalizer();
        $this->expectException(\InvalidArgumentException::class);
        $normalizer->normalize('SIMPLE', "safe\u{202E}unsafe");
    }
    public function testRowChecksumIsDeterministicAndOrderBound(): void
    {
        self::assertSame(QuranSearchHasher::row(1, 1, 'بسم'), QuranSearchHasher::row(1, 1, 'بسم'));
        self::assertNotSame(QuranSearchHasher::row(1, 1, 'بسم'), QuranSearchHasher::row(1, 2, 'بسم'));
    }
}
