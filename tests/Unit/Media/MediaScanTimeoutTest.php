<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Media;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Modules\MediaProcessing\Domain\MediaScanTimeout;

final class MediaScanTimeoutTest extends TestCase
{
    public function testDefaultAndMaximumRemainInsideFencedJobLease(): void
    {
        self::assertSame(30, (new MediaScanTimeout(null))->seconds);
        self::assertSame(240, (new MediaScanTimeout('240'))->seconds);
        self::assertLessThan(300, (new MediaScanTimeout('240'))->seconds);
    }

    #[DataProvider('invalidTimeouts')]
    public function testInvalidConfigurationFailsClosed(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MediaScanTimeout($value);
    }

    /** @return iterable<string,array{string}> */
    public static function invalidTimeouts(): iterable
    {
        foreach (['', '0', '-1', '241', '300', '99999999999999', '1.5', '30 seconds', ' 30', '030'] as $value) {
            yield 'invalid ' . $value => [$value];
        }
    }
}
