<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\Http;

use PHPUnit\Framework\TestCase;
use Qmdb\Bootstrap\Http\HttpRuntime;
use Qmdb\Shared\Http\Request\NativeServerRequestFactory;
use Qmdb\Tests\Support\Http\HttpTestFactory;
use Qmdb\Tests\Support\Http\ProductionHttpRuntimeFactory;
use ReflectionClass;

final class ApplicationFactoryHttpRuntimeTest extends TestCase
{
    public function testFullRuntimeConstructsWithTypedBoundaries(): void
    {
        $runtime = ProductionHttpRuntimeFactory::create();

        self::assertInstanceOf(HttpRuntime::class, $runtime);
        self::assertInstanceOf(NativeServerRequestFactory::class, $runtime->requestFactory());
        self::assertSame(200, $runtime->handle(HttpTestFactory::request('GET', '/'))->getStatusCode());
    }

    public function testRuntimeIsNotAGeneralServiceLocator(): void
    {
        $reflection = new ReflectionClass(HttpRuntime::class);

        self::assertFalse($reflection->hasMethod('get'));
        self::assertFalse($reflection->hasMethod('has'));
        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
    }

    public function testLegacyDatabaseAndFlatDomainRootsRemainAbsent(): void
    {
        self::assertDirectoryDoesNotExist(dirname(__DIR__, 3) . '/src/Database');
        self::assertDirectoryDoesNotExist(dirname(__DIR__, 3) . '/src/Domain');
    }
}
