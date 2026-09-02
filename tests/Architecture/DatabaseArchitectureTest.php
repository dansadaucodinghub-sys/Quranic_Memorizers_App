<?php

declare(strict_types=1);

namespace Qmdb\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class DatabaseArchitectureTest extends TestCase
{
    public function testPdoIsLimitedToApprovedPersistenceBoundaries(): void
    {
        foreach ($this->phpFiles($this->root() . '/src') as $path) {
            $source = $this->read($path);
            if (!str_contains($source, 'PDO')) {
                continue;
            }

            $normalized = str_replace('\\', '/', $path);
            self::assertTrue(
                str_contains($normalized, '/Shared/Infrastructure/Persistence/MySql/')
                || str_contains($normalized, '/Modules/Identity/Infrastructure/Persistence/')
                || str_contains($normalized, '/Modules/IdentityResolution/Infrastructure/Persistence/')
                || str_contains($normalized, '/Modules/Geography/Infrastructure/Persistence/')
                || str_contains($normalized, '/Modules/People/Infrastructure/Persistence/')
                || str_contains($normalized, '/Modules/Organizations/Infrastructure/Persistence/')
                || str_contains($normalized, '/Modules/OrganizationAffiliations/Infrastructure/Persistence/')
                || str_contains($normalized, '/Modules/IdentityAccess/Infrastructure/Persistence/')
                || str_contains($normalized, '/Modules/IdentityAccountState/Infrastructure/Persistence/')
                || str_contains($normalized, '/Modules/IdentityMultiFactor/Infrastructure/Persistence/')
                || str_contains($normalized, '/Modules/IdentityRecovery/Infrastructure/Persistence/')
                || str_contains($normalized, '/Modules/IdentitySecurityNotifications/Infrastructure/Persistence/')
                || str_contains($normalized, '/Modules/IdentitySessions/Infrastructure/Persistence/')
                || str_contains($normalized, '/Modules/SecurityAuthorization/Infrastructure/Persistence/')
                || str_contains($normalized, '/Modules/SecurityAudit/Infrastructure/Persistence/')
                || str_contains($normalized, '/Modules/SecurityPrivilegedAccess/Infrastructure/Persistence/')
                || str_contains($normalized, '/Modules/Tenancy/Infrastructure/Persistence/')
                || str_contains($normalized, '/Modules/TenancyContext/Infrastructure/Persistence/')
                || str_ends_with($normalized, '/Shared/Database/Connection/DatabaseConnectionProvider.php')
                || str_contains($normalized, '/Shared/Schema/')
                || str_contains($normalized, '/Shared/Background/Scheduler/Infrastructure/'),
                'PDO dependency escaped the approved database boundary: ' . $normalized,
            );
        }
    }

    public function testApplicationDomainAndControllersDoNotDependOnMySqlInfrastructure(): void
    {
        $paths = array_merge(
            $this->phpFiles($this->root() . '/src/Shared/Application'),
            $this->phpFiles($this->root() . '/src/Shared/Domain'),
            $this->phpFiles($this->root() . '/src/Shared/Http/Controller'),
        );
        foreach ($paths as $path) {
            $source = $this->read($path);
            self::assertStringNotContainsString('Infrastructure\\Persistence\\MySql', $source, $path);
            self::assertStringNotContainsString('use PDO;', $source, $path);
            self::assertStringNotContainsString('DatabaseConnectionProvider', $source, $path);
        }
    }

    public function testNoOrmQueryBuilderOrBusinessMigrationWasIntroduced(): void
    {
        $composer = $this->read($this->root() . '/composer.json');
        foreach (['doctrine/', 'illuminate/database', 'cycle/orm', 'laminas/laminas-db'] as $package) {
            self::assertStringNotContainsString($package, strtolower($composer));
        }

        self::assertFileExists($this->root() . '/database/migrations.php');
        self::assertFileExists($this->root() . '/database/seeds.php');
        self::assertSame(1, count(glob($this->root() . '/database/migrations/*') ?: []));
        self::assertSame(1, count(glob($this->root() . '/database/seeds/*') ?: []));
        self::assertDirectoryDoesNotExist($this->root() . '/src/Modules/Database');
    }

    public function testDatabaseModuleAndHttpDependencyAreExplicit(): void
    {
        $databaseModule = $this->read($this->root() . '/src/Bootstrap/Module/DatabaseFoundationModule.php');
        $httpModule = $this->read($this->root() . '/src/Bootstrap/Module/HttpFoundationModule.php');

        self::assertStringContainsString("private const ID = 'foundation.database'", $databaseModule);
        self::assertStringContainsString("new ModuleId('foundation.core')", $databaseModule);
        self::assertStringContainsString("new ModuleId('foundation.database')", $httpModule);
    }

    public function testNoProductionFrontendOrLongLivedTransactionMechanismWasIntroduced(): void
    {
        self::assertDirectoryDoesNotExist($this->root() . '/assets/js');
        foreach ($this->phpFiles($this->root() . '/src') as $path) {
            $source = $this->read($path);
            self::assertStringNotContainsString('EventSource', $source, $path);
            self::assertStringNotContainsString('text/event-stream', $source, $path);
        }
    }

    /** @return list<string> */
    private function phpFiles(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        $paths = [];
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $paths[] = $file->getPathname();
            }
        }

        return $paths;
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private function read(string $path): string
    {
        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }
}
