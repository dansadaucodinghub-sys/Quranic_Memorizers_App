<?php

declare(strict_types=1);

namespace Qmdb\Tests\Tools\Security;

use PHPUnit\Framework\TestCase;
use Qmdb\Tools\Security\SensitiveContentScanner;
use Qmdb\Tools\Support\FileSystem;

final class SensitiveContentScannerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/qmdb-secret-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0755, true);
    }

    protected function tearDown(): void
    {
        FileSystem::removeTree(dirname($this->directory), $this->directory);
    }

    public function testCleanContentPasses(): void
    {
        file_put_contents($this->directory . '/safe.txt', 'example-value');
        self::assertSame([], (new SensitiveContentScanner())->scanDirectory($this->directory));
    }

    public function testPrivateKeyMarkerIsDetected(): void
    {
        file_put_contents($this->directory . '/unsafe.txt', '-----BEGIN ' . 'PRIVATE KEY-----');
        self::assertSame(['unsafe.txt: private key'], (new SensitiveContentScanner())->scanDirectory($this->directory));
    }

    public function testDependencyDirectoriesAreExcludedByDefault(): void
    {
        mkdir($this->directory . '/vendor', 0755);
        file_put_contents($this->directory . '/vendor/fixture.txt', '-----BEGIN ' . 'PRIVATE KEY-----');
        self::assertSame([], (new SensitiveContentScanner())->scanDirectory($this->directory));
        self::assertNotSame([], (new SensitiveContentScanner())->scanDirectory($this->directory, false));
    }

    public function testPortableRuntimeDirectoryIsAlwaysExcluded(): void
    {
        mkdir($this->directory . '/.runtime', 0755);
        file_put_contents($this->directory . '/.runtime/generated.pem', '-----BEGIN ' . 'PRIVATE KEY-----');

        self::assertSame([], (new SensitiveContentScanner())->scanDirectory($this->directory));
        self::assertSame([], (new SensitiveContentScanner())->scanDirectory($this->directory, false));
    }
}
