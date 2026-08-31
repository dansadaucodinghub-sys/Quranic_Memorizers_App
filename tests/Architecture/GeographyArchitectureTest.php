<?php

declare(strict_types=1);

namespace Qmdb\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class GeographyArchitectureTest extends TestCase
{
    public function testGeographyModuleDependsOnlyOnTheApprovedFoundationBoundaries(): void
    {
        $root = dirname(__DIR__, 2);
        $module = file_get_contents($root . '/src/Bootstrap/Module/GeographyReferenceModule.php');
        self::assertIsString($module);
        self::assertStringContainsString("private const ID = 'reference.geography';", $module);
        foreach (['Identity', 'Tenancy', 'Authorization', 'Organizations', 'People', 'Guardianship', 'Competition'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $module);
        }
    }

    public function testGeographyHasNoWriteControllersOrForbiddenFutureDomainImports(): void
    {
        $root = dirname(__DIR__, 2) . '/src/Modules/Geography';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            $source = file_get_contents($file->getPathname());
            self::assertIsString($source);
            foreach (['Organizations', 'People', 'Guardianship', 'Competition', 'WorkspaceContext', 'PermissionDecision'] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $file->getFilename());
            }
            if (str_contains($file->getPathname(), DIRECTORY_SEPARATOR . 'Interface' . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR)) {
                self::assertStringNotContainsString('HttpMethod::POST', $source, $file->getFilename());
                self::assertStringNotContainsString('HttpMethod::PUT', $source, $file->getFilename());
                self::assertStringNotContainsString('HttpMethod::DELETE', $source, $file->getFilename());
                self::assertStringNotContainsString('PDO', $source, $file->getFilename());
            }
        }
    }
}
