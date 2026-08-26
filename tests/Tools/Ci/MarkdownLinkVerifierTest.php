<?php

declare(strict_types=1);

namespace Qmdb\Tests\Tools\Ci;

use PHPUnit\Framework\TestCase;
use Qmdb\Tools\Ci\MarkdownLinkVerifier;
use Qmdb\Tools\Support\FileSystem;

final class MarkdownLinkVerifierTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/qmdb-links-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0755, true);
    }

    protected function tearDown(): void
    {
        FileSystem::removeTree(dirname($this->directory), $this->directory);
    }

    public function testExistingRelativeFileAndAnchorPass(): void
    {
        file_put_contents($this->directory . '/target.md', "# Target Heading\n");
        file_put_contents($this->directory . '/source.md', "[target](target.md#target-heading)\n");
        self::assertTrue((new MarkdownLinkVerifier($this->directory))->verify()->passed());
    }

    public function testMissingTargetFails(): void
    {
        file_put_contents($this->directory . '/source.md', "[missing](absent.md)\n");
        $report = (new MarkdownLinkVerifier($this->directory))->verify();
        self::assertFalse($report->passed());
        self::assertStringContainsString('broken link target', $report->errors()[0]);
    }
}
