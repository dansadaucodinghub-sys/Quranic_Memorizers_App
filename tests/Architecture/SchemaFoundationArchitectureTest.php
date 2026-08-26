<?php

declare(strict_types=1);

namespace Qmdb\Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\Identity\Infrastructure\Migration\CreateAccountSecurityFoundationMigration;
use Qmdb\Modules\Identity\Infrastructure\Migration\CreateUserAccountsMigration;
use Qmdb\Modules\IdentityAccess\Infrastructure\Migration\CreateIdentityRateLimitFoundationMigration;
use Qmdb\Modules\IdentityAccess\Infrastructure\Migration\CreateIdentityVerificationFoundationMigration;
use Qmdb\Modules\Tenancy\Infrastructure\Migration\CreateWorkspaceMembershipsMigration;
use Qmdb\Modules\Tenancy\Infrastructure\Migration\CreateWorkspacesMigration;
use Qmdb\Shared\Background\Scheduler\Migration\CreateScheduledTaskRunsMigration;
use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationRegistry;
use Qmdb\Shared\Schema\Seed\SeedRegistry;
use SplFileInfo;

final class SchemaFoundationArchitectureTest extends TestCase
{
    public function testProductionManifestsContainOnlyAuthorizedP1ThroughP2B02Migrations(): void
    {
        $migrationFactory = require dirname(__DIR__, 2) . '/database/migrations.php';
        $seedFactory = require dirname(__DIR__, 2) . '/database/seeds.php';

        if (!is_callable($migrationFactory) || !is_callable($seedFactory)) {
            self::fail('Production schema manifests must return callable factories.');
        }
        $migrations = $migrationFactory();
        $seeds = $seedFactory();
        if (!$migrations instanceof MigrationRegistry || !$seeds instanceof SeedRegistry) {
            self::fail('Production schema manifests returned invalid registries.');
        }

        $ordered = $migrations->ordered();
        self::assertCount(7, $ordered);
        self::assertSame(
            [
                CreateScheduledTaskRunsMigration::class,
                CreateWorkspacesMigration::class,
                CreateUserAccountsMigration::class,
                CreateAccountSecurityFoundationMigration::class,
                CreateWorkspaceMembershipsMigration::class,
                CreateIdentityVerificationFoundationMigration::class,
                CreateIdentityRateLimitFoundationMigration::class,
            ],
            array_map(static fn (Migration $migration): string => $migration::class, $ordered),
        );
        self::assertSame(
            [
                '20260825000100_create_scheduled_task_runs',
                '20260826010100_create_workspaces',
                '20260826010200_create_user_accounts',
                '20260826010300_create_account_security_foundation',
                '20260826010400_create_workspace_memberships',
                '20260826010500_create_identity_verification_foundation',
                '20260826010600_create_identity_rate_limit_foundation',
            ],
            array_map(static fn (Migration $migration): string => $migration->id()->value(), $ordered),
        );
        self::assertSame([], $seeds->ordered());
    }

    public function testNoDiscoveryOrHttpMutationSurfaceExists(): void
    {
        $root = dirname(__DIR__, 2);
        $schemaSource = $this->sourceUnder($root . '/src/Shared/Schema');
        $routeSource = (string) file_get_contents($root . '/routes/web.php');

        self::assertStringNotContainsString('glob(', $schemaSource);
        self::assertStringNotContainsString('RecursiveDirectoryIterator', $schemaSource);
        self::assertStringNotContainsString('ReflectionClass', $schemaSource);
        self::assertStringNotContainsString('SET FOREIGN_KEY_CHECKS', $this->frameworkSqlOnly($schemaSource));
        self::assertStringNotContainsString('SET GLOBAL', $this->frameworkSqlOnly($schemaSource));
        self::assertStringNotContainsString('db:migrate', $routeSource);
        self::assertStringNotContainsString('db:seed', $routeSource);
    }

    public function testNoFrontendOrThirdPartyMigrationFrameworkWasIntroduced(): void
    {
        $root = dirname(__DIR__, 2);
        $composer = (string) file_get_contents($root . '/composer.json');
        self::assertStringNotContainsString('doctrine/migrations', strtolower($composer));
        self::assertStringNotContainsString('robmorgan/phinx', strtolower($composer));
        self::assertDirectoryDoesNotExist($root . '/src/Shared/Schema/Http');
        self::assertDirectoryDoesNotExist($root . '/src/Shared/Schema/Frontend');
    }

    public function testSchemaProviderUsesPhp85PdoMysqlConstants(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 2) . '/src/Shared/Schema/Connection/MySqlSchemaConnectionProvider.php',
        );

        self::assertStringNotContainsString('PDO::MYSQL_ATTR_', $source);
        self::assertStringContainsString('\\Pdo\\Mysql::ATTR_MULTI_STATEMENTS', $source);
        self::assertStringContainsString('\\Pdo\\Mysql::ATTR_SSL_CA', $source);
        self::assertStringContainsString('\\Pdo\\Mysql::ATTR_SSL_VERIFY_SERVER_CERT', $source);
    }

    private function sourceUnder(string $directory): string
    {
        $source = '';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $source .= (string) file_get_contents($file->getPathname());
            }
        }

        return $source;
    }

    private function frameworkSqlOnly(string $source): string
    {
        return str_replace([
            "'SET FOREIGN_KEY_CHECKS'",
            "'SET GLOBAL'",
            'SET\\s+FOREIGN_KEY_CHECKS',
            'SET\\s+GLOBAL',
        ], '', $source);
    }
}
