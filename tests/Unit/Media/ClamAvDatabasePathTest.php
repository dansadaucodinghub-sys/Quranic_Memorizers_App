<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Media;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\MediaProcessing\Infrastructure\Security\ClamAvMediaScanner;

final class ClamAvDatabasePathTest extends TestCase
{
    public function testConfiguredDatabaseUsesCanonicalNativeSeparators(): void
    {
        $path = __DIR__ . '/../../..';
        $scanner = new ClamAvMediaScanner(PHP_BINARY, 30, $path);
        $method = new \ReflectionMethod($scanner, 'databaseArguments');
        self::assertSame(['--database=' . realpath($path)], $method->invoke($scanner));
    }

    public function testMissingDatabaseFailsClosed(): void
    {
        $scanner = new ClamAvMediaScanner(PHP_BINARY, 30, __DIR__ . '/nonexistent-' . bin2hex(random_bytes(8)));
        self::assertFalse($scanner->check()['healthy']);
    }

    public function testSystemDatabaseRemainsAvailableWhenNoOverrideIsConfigured(): void
    {
        $scanner = new ClamAvMediaScanner(PHP_BINARY);
        $method = new \ReflectionMethod($scanner, 'databaseArguments');
        self::assertSame([], $method->invoke($scanner));
    }
}
