<?php

declare(strict_types=1);

namespace Qmdb\Tests\Tools\Build;

use PHPUnit\Framework\TestCase;
use Qmdb\Tools\Build\ReleaseManifestGenerator;
use Qmdb\Tools\Support\FileSystem;
use Qmdb\Tools\Support\GitMetadata;

final class ReleaseManifestTest extends TestCase
{
    private string $stage;

    protected function setUp(): void
    {
        $this->stage = sys_get_temp_dir() . '/qmdb-manifest-' . bin2hex(random_bytes(8));
        mkdir($this->stage . '/src', 0755, true);
        file_put_contents($this->stage . '/README.md', "QMDB\n");
        file_put_contents($this->stage . '/src/Example.php', "<?php\n");
    }

    protected function tearDown(): void
    {
        FileSystem::removeTree(dirname($this->stage), $this->stage);
    }

    public function testManifestContainsSortedFileHashesAndGovernanceMetadata(): void
    {
        $git = new GitMetadata(str_repeat('a', 40), str_repeat('a', 12), 1_700_000_000, 'dirty');
        $manifest = (new ReleaseManifestGenerator())->generate(
            $this->stage,
            $git,
            str_repeat('b', 64),
            str_repeat('c', 64),
            str_repeat('d', 64),
            str_repeat('e', 64),
        );
        $data = $manifest->data();
        self::assertSame('QMDB-P0-FRZ-001', $data['frozen_baseline']);
        self::assertFalse($data['release_eligible']);
        self::assertSame(['README.md', 'src/Example.php'], array_column($manifest->files(), 'path'));
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $manifest->files()[0]['sha256']);
    }

    public function testManifestRejectsSymlinkOrForbiddenPath(): void
    {
        file_put_contents($this->stage . '/tests.php', '<?php');
        $this->expectException(\RuntimeException::class);
        $git = new GitMetadata(str_repeat('a', 40), str_repeat('a', 12), 1, 'clean');
        (new ReleaseManifestGenerator())->generate($this->stage, $git, '', '', '', '');
    }
}
