<?php

declare(strict_types=1);

namespace Qmdb\Tests\Tools\Build;

use PHPUnit\Framework\TestCase;
use Qmdb\Tools\Build\ReleaseArchiveBuilder;
use Qmdb\Tools\Build\TarArchiveReader;
use Qmdb\Tools\Support\FileSystem;

final class ReleaseArchiveTest extends TestCase
{
    private string $temporaryRoot;

    protected function setUp(): void
    {
        $this->temporaryRoot = sys_get_temp_dir() . '/qmdb-archive-' . bin2hex(random_bytes(8));
        mkdir($this->temporaryRoot . '/stage/src', 0755, true);
        file_put_contents($this->temporaryRoot . '/stage/README.md', "QMDB\n");
        file_put_contents($this->temporaryRoot . '/stage/src/Example.php', "<?php\n");
    }

    protected function tearDown(): void
    {
        FileSystem::removeTree(dirname($this->temporaryRoot), $this->temporaryRoot);
    }

    public function testArchiveIsDeterministicAndReadable(): void
    {
        $builder = new ReleaseArchiveBuilder();
        $first = $this->temporaryRoot . '/first.tar.gz';
        $second = $this->temporaryRoot . '/second.tar.gz';
        $firstHash = $builder->build($this->temporaryRoot . '/stage', $first, 1_700_000_000);
        $secondHash = $builder->build($this->temporaryRoot . '/stage', $second, 1_700_000_000);
        self::assertSame($firstHash, $secondHash);
        self::assertSame(file_get_contents($first), file_get_contents($second));
        $entries = (new TarArchiveReader())->entries($first);
        self::assertSame("QMDB\n", $entries['README.md']['content']);
    }

    public function testArchiveBuilderRejectsUnexpectedFile(): void
    {
        file_put_contents($this->temporaryRoot . '/stage/.env', 'APP_KEY=secret');
        $this->expectException(\RuntimeException::class);
        (new ReleaseArchiveBuilder())->build(
            $this->temporaryRoot . '/stage',
            $this->temporaryRoot . '/bad.tar.gz',
            1_700_000_000,
        );
    }
}
