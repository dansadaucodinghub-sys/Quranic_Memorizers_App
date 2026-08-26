<?php

declare(strict_types=1);

namespace Qmdb\Tests\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ApplicationServicesArchitectureTest extends TestCase
{
    public function testContainerContractDoesNotLeakIntoApplicationHttpOrDomainLayers(): void
    {
        foreach (['Shared/Application', 'Shared/Http', 'Shared/Domain'] as $directory) {
            foreach ($this->sourceFiles($directory) as $path) {
                self::assertStringNotContainsString('Psr\\Container', $this->read($path), $path);
                self::assertStringNotContainsString('CompiledContainer', $this->read($path), $path);
            }
        }
    }

    public function testDomainHasNoUpwardLayerDependency(): void
    {
        foreach ($this->sourceFiles('Shared/Domain') as $path) {
            $source = $this->read($path);
            foreach (
                [
                    'Qmdb\\Bootstrap',
                    'Qmdb\\Shared\\Application',
                    'Qmdb\\Shared\\Http',
                    'Qmdb\\Shared\\DependencyInjection',
                    'Qmdb\\Shared\\Module',
                ] as $forbidden
            ) {
                self::assertStringNotContainsString($forbidden, $source, $path);
            }
        }
    }

    public function testApplicationHasNoHttpModuleOrContainerDependency(): void
    {
        foreach ($this->sourceFiles('Shared/Application') as $path) {
            $source = $this->read($path);
            foreach (
                ['Psr\\Http', 'Psr\\Container', 'Qmdb\\Shared\\Http', 'Qmdb\\Shared\\Module'] as $forbidden
            ) {
                self::assertStringNotContainsString($forbidden, $source, $path);
            }
        }
    }

    public function testDependencyInjectionLayerRemainsGeneric(): void
    {
        foreach ($this->sourceFiles('Shared/DependencyInjection') as $path) {
            $source = $this->read($path);
            $forbiddenDependencies = [
                'Qmdb\\Shared\\Http',
                'Qmdb\\Shared\\Application',
                'Qmdb\\Shared\\Domain',
                'PDO',
                'Redis',
            ];
            foreach ($forbiddenDependencies as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $path);
            }
        }
    }

    public function testNoReflectionAutowiringOrFilesystemDiscoveryExists(): void
    {
        foreach ($this->sourceFiles('') as $path) {
            self::assertDoesNotMatchRegularExpression(
                '/(?:ReflectionClass|RecursiveDirectoryIterator|glob\s*\(|scandir\s*\()/i',
                $this->read($path),
                $path,
            );
        }
    }

    public function testNoStaticServiceLocatorOrInjectionMutatorsExist(): void
    {
        foreach ($this->sourceFiles('') as $path) {
            $source = $this->read($path);
            self::assertDoesNotMatchRegularExpression('/function\s+getService\s*\(/i', $source, $path);
            self::assertDoesNotMatchRegularExpression('/function\s+set[A-Z][A-Za-z0-9]*\s*\(/', $source, $path);
        }
    }

    public function testOnlyCompositionRootResolvesFromCompiledContainer(): void
    {
        foreach ($this->sourceFiles('Bootstrap') as $path) {
            if (!str_ends_with($path, '/Bootstrap/ApplicationFactory.php')) {
                self::assertStringNotContainsString('CompiledContainer', $this->read($path), $path);
            }
        }
    }

    public function testFoundationModuleIdentifiersAreExplicitAndFixed(): void
    {
        $moduleDirectory = dirname(__DIR__, 2) . '/src/Bootstrap/Module';
        $source = '';
        foreach ($this->filesUnder($moduleDirectory) as $path) {
            $source .= $this->read($path);
        }

        foreach (
            [
                'foundation.core',
                'foundation.application',
                'foundation.http',
                'foundation.console',
                'identity.accounts',
                'tenancy.workspaces',
            ] as $moduleId
        ) {
            self::assertStringContainsString($moduleId, $source);
        }
        self::assertStringNotContainsString('glob(', $source);
        self::assertStringNotContainsString('scandir(', $source);
    }

    /** @return list<string> */
    private function sourceFiles(string $relativeDirectory): array
    {
        $root = dirname(__DIR__, 2) . '/src';
        $directory = $relativeDirectory === '' ? $root : $root . '/' . $relativeDirectory;

        return $this->filesUnder($directory);
    }

    /** @return list<string> */
    private function filesUnder(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = str_replace('\\', '/', $file->getPathname());
            }
        }
        sort($files);

        return $files;
    }

    private function read(string $path): string
    {
        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }
}
