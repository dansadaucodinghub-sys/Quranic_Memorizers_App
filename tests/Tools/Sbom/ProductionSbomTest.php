<?php

declare(strict_types=1);

namespace Qmdb\Tests\Tools\Sbom;

use PHPUnit\Framework\TestCase;
use Qmdb\Tools\Sbom\ComposerInventory;
use Qmdb\Tools\Sbom\ProductionSbomGenerator;
use Qmdb\Tools\Sbom\SbomValidator;
use Qmdb\Tools\Support\FileSystem;
use Qmdb\Tools\Support\GitMetadata;

final class ProductionSbomTest extends TestCase
{
    public function testSbomContainsRuntimeDependenciesOnlyAndIsDeterministic(): void
    {
        $root = dirname(__DIR__, 3);
        $git = new GitMetadata(str_repeat('a', 40), str_repeat('a', 12), 1_700_000_000, 'dirty');
        $generator = new ProductionSbomGenerator();
        $first = $generator->generate($root, $git);
        $second = $generator->generate($root, $git);
        self::assertSame($first, $second);
        $inventory = (new ComposerInventory())->packages($root . '/composer.lock');
        $components = $first['components'] ?? null;
        self::assertIsArray($components);
        self::assertCount(count($inventory['runtime']), $components);
        self::assertGreaterThan(0, count($inventory['development']));
    }

    public function testWrittenSbomHasChecksumAndValidates(): void
    {
        $root = dirname(__DIR__, 3);
        $directory = sys_get_temp_dir() . '/qmdb-sbom-' . bin2hex(random_bytes(8));
        mkdir($directory, 0755, true);
        try {
            $path = $directory . '/bom.json';
            $git = new GitMetadata(str_repeat('b', 40), str_repeat('b', 12), 1, 'clean');
            $hash = (new ProductionSbomGenerator())->write($root, $path, $git);
            self::assertSame($hash, hash_file('sha256', $path));
            self::assertFileExists($path . '.sha256');
            self::assertTrue((new SbomValidator())->validate($root, $path)->passed());
        } finally {
            FileSystem::removeTree(dirname($directory), $directory);
        }
    }

    public function testValidatorRejectsDevelopmentComponent(): void
    {
        $root = dirname(__DIR__, 3);
        $directory = sys_get_temp_dir() . '/qmdb-sbom-invalid-' . bin2hex(random_bytes(8));
        mkdir($directory, 0755, true);
        try {
            $path = $directory . '/bom.json';
            $git = new GitMetadata(str_repeat('c', 40), str_repeat('c', 12), 1, 'clean');
            $bom = (new ProductionSbomGenerator())->generate($root, $git);
            $development = (new ComposerInventory())->packages($root . '/composer.lock')['development'][0];
            $reference = 'pkg:composer/' . $development['name'] . '@' . rawurlencode($development['version']);
            $components = $bom['components'] ?? null;
            self::assertIsArray($components);
            $components[] = [
                'type' => 'library',
                'bom-ref' => $reference,
                'purl' => $reference,
            ];
            $bom['components'] = $components;
            file_put_contents($path, json_encode($bom, JSON_THROW_ON_ERROR));
            self::assertFalse((new SbomValidator())->validate($root, $path)->passed());
        } finally {
            FileSystem::removeTree(dirname($directory), $directory);
        }
    }
}
