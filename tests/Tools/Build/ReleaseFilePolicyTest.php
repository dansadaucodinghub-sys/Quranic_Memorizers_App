<?php

declare(strict_types=1);

namespace Qmdb\Tests\Tools\Build;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Tools\Build\ReleaseFilePolicy;

final class ReleaseFilePolicyTest extends TestCase
{
    #[DataProvider('allowedPaths')]
    public function testAllowsOnlyGovernedRuntimePaths(string $path): void
    {
        self::assertTrue((new ReleaseFilePolicy())->isAllowed($path));
    }

    #[DataProvider('forbiddenPaths')]
    public function testRejectsDevelopmentAndSensitivePaths(string $path): void
    {
        self::assertFalse((new ReleaseFilePolicy())->isAllowed($path));
    }

    public function testOnlyConsoleEntrypointIsExecutable(): void
    {
        $policy = new ReleaseFilePolicy();
        self::assertSame(0755, $policy->mode('bin/console'));
        self::assertSame(0644, $policy->mode('public/index.php'));
    }

    public function testReleaseFilenameIsBounded(): void
    {
        $policy = new ReleaseFilePolicy();
        self::assertTrue($policy->validateArchiveFilename('qmdb-0.1.0-dev-a1b2c3d4e5f6.tar.gz'));
        self::assertFalse($policy->validateArchiveFilename('qmdb-latest.tar.gz'));
    }

    public function testVersionTagRequiresSemverShape(): void
    {
        $policy = new ReleaseFilePolicy();
        self::assertTrue($policy->validateVersionTag('v1.2.3-rc.1'));
        self::assertFalse($policy->validateVersionTag('latest'));
    }

    /** @return iterable<string, array{string}> */
    public static function allowedPaths(): iterable
    {
        yield 'source' => ['src/Bootstrap/Application.php'];
        yield 'runtime vendor' => ['vendor/composer/autoload_real.php'];
        yield 'exact SBOM' => ['metadata/production-sbom.cdx.json'];
        yield 'manifest' => ['release-manifest.json'];
    }

    /** @return iterable<string, array{string}> */
    public static function forbiddenPaths(): iterable
    {
        yield 'environment' => ['.env'];
        yield 'tests' => ['tests/Unit/ExampleTest.php'];
        yield 'CI' => ['.github/workflows/ci.yml'];
        yield 'tools' => ['tools/build/build-release.php'];
        yield 'unapproved metadata' => ['metadata/arbitrary.json'];
        yield 'private key' => ['config/private.key'];
    }
}
