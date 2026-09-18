<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Media;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\MediaProcessing\Infrastructure\Process\BoundedMediaProcess;

final class BoundedMediaProcessTest extends TestCase
{
    public function testExitCodeIsPreservedAndBothPipesAreDrained(): void
    {
        $result = BoundedMediaProcess::run([PHP_BINARY, '-r', 'fwrite(STDERR,str_repeat("x",65536)); fwrite(STDOUT,"verified"); exit(7);'], 30);
        self::assertSame(7, $result['exit']);
        self::assertSame('verified', $result['stdout']);
    }

    public function testDeadlineTerminatesAStalledAdapter(): void
    {
        $this->expectException(\RuntimeException::class);
        BoundedMediaProcess::run([PHP_BINARY, '-r', 'sleep(10);'], 1);
    }

    public function testOutputFloodFailsClosed(): void
    {
        $this->expectException(\RuntimeException::class);
        BoundedMediaProcess::run([PHP_BINARY, '-r', 'for($i=0;$i<64;$i++){fwrite(STDOUT,str_repeat("x",65536));}'], 30);
    }
}
