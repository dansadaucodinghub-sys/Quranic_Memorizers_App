<?php

declare(strict_types=1);

namespace Qmdb\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class SecurityAuthorizationArchitectureTest extends TestCase
{
    public function testAuthorizationModuleIsExplicitAndContainsNoDynamicPolicyEngine(): void
    {
        $root = $this->root();
        $source = $this->sourceUnder($root . '/src/Modules/SecurityAuthorization');
        $catalog = $this->read(
            $root . '/src/Modules/SecurityAuthorization/Domain/AuthorizationCatalogRegistry.php',
        );
        $composer = strtolower($this->read($root . '/composer.json'));

        self::assertStringNotContainsString("'*'", $catalog);
        self::assertStringNotContainsString('role_parent', strtolower($source));
        self::assertStringNotContainsString('parent_role', strtolower($source));
        self::assertDoesNotMatchRegularExpression('/\b(?:eval|exec|shell_exec|system|passthru)\s*\(/i', $source);
        self::assertStringNotContainsString('policy_expression', strtolower($source));
        foreach (['casbin', 'oso/', 'symfony/security-acl', 'doctrine/', 'illuminate/database'] as $package) {
            self::assertStringNotContainsString($package, $composer);
        }
    }

    public function testDomainIsIndependentFromHttpPdoAndInfrastructure(): void
    {
        $domain = $this->sourceUnder($this->root() . '/src/Modules/SecurityAuthorization/Domain');

        self::assertStringNotContainsString('Psr\Http', $domain);
        self::assertStringNotContainsString('Qmdb\Shared\Http', $domain);
        self::assertStringNotContainsString('use PDO;', $domain);
        self::assertStringNotContainsString('Infrastructure\Persistence', $domain);
        self::assertStringNotContainsString('DatabaseConnectionProvider', $domain);
    }

    public function testNoProductionRoleManagementOrWorkspaceSwitchSurfaceExists(): void
    {
        $root = $this->root();
        $routes = $this->read($root . '/routes/web.php');

        self::assertDirectoryDoesNotExist($root . '/src/Modules/SecurityAuthorization/Interface/Http');
        self::assertStringNotContainsString('role-management', strtolower($routes));
        self::assertStringNotContainsString('permission-management', strtolower($routes));
        self::assertStringNotContainsString('workspace-switch', strtolower($routes));
        self::assertDirectoryDoesNotExist($root . '/src/Modules/SecurityAuthorization/Application/CustomRole');
    }

    public function testWorkspacePersistenceRequiresTrustedTenantContextAndHasNoUnscopedLookup(): void
    {
        $interface = $this->read(
            $this->root()
                . '/src/Modules/SecurityAuthorization/Domain/Repository/WorkspaceRoleAssignmentRepository.php',
        );
        $implementation = $this->read(
            $this->root()
                . '/src/Modules/SecurityAuthorization/Infrastructure/Persistence/'
                . 'MySqlWorkspaceRoleAssignmentRepository.php',
        );

        self::assertGreaterThanOrEqual(7, substr_count($interface, 'TenantContext $context'));
        self::assertStringContainsString('workspace_id = :workspace_id', $implementation);
        self::assertStringContainsString('workspaceInternalId()', $implementation);
        self::assertStringNotContainsString('findByPublicId(', $interface);
        self::assertStringNotContainsString('findByInternalId(', $interface);
    }

    public function testAuthenticationTenantAndAuthorizationContextsRemainDistinct(): void
    {
        $subject = $this->read(
            $this->root() . '/src/Modules/SecurityAuthorization/Application/AuthorizationSubject.php',
        );
        $scope = $this->read(
            $this->root() . '/src/Modules/SecurityAuthorization/Domain/WorkspaceAuthorizationScope.php',
        );
        $decision = $this->read(
            $this->root() . '/src/Modules/SecurityAuthorization/Application/RoleBasedAuthorizationService.php',
        );

        self::assertStringContainsString('AuthenticatedAccountContext', $subject);
        self::assertStringNotContainsString('TenantContext', $subject);
        self::assertStringContainsString('TenantContext', $scope);
        self::assertStringContainsString('findEffectiveWorkspacePermission', $decision);
        self::assertStringContainsString('findEffectivePlatformPermission', $decision);
        self::assertStringContainsString('DENIED_NO_ROLE_ASSIGNMENT', $decision);
    }

    public function testMigrationsAndProductionSeedAreExplicitlyRegistered(): void
    {
        $migrations = $this->read($this->root() . '/database/migrations.php');
        $seeds = $this->read($this->root() . '/database/seeds.php');

        foreach (
            [
            'CreateAuthorizationCatalogFoundationMigration',
            'CreatePlatformRoleAssignmentFoundationMigration',
            'CreateWorkspaceRoleAssignmentFoundationMigration',
            ] as $migration
        ) {
            self::assertStringContainsString($migration, $migrations);
        }
        self::assertStringContainsString('SeedFoundationalAuthorizationCatalog', $seeds);
        self::assertStringNotContainsString('PlatformRoleAssignment', $seeds);
        self::assertStringNotContainsString('WorkspaceRoleAssignment', $seeds);
    }

    public function testLocalCiRestoresCanonicalSchemaAfterDestructiveMySqlTests(): void
    {
        $runner = $this->read($this->root() . '/tools/Ci/LocalCiRunner.php');
        $reset = $this->read($this->root() . '/tools/Ci/reset-test-schema.php');

        $mysqlTests = strpos($runner, "['mysql-tests', ['composer', 'test:mysql']]");
        $schemaReset = strpos($runner, "['mysql-schema-reset', ['php', 'tools/ci/reset-test-schema.php']]");
        $releaseVerify = strpos($runner, "['release-verify', ['php', 'tools/build/verify-release.php']]");
        self::assertIsInt($mysqlTests);
        self::assertIsInt($schemaReset);
        self::assertIsInt($releaseVerify);
        self::assertGreaterThan($mysqlTests, $schemaReset);
        self::assertGreaterThan($schemaReset, $releaseVerify);
        self::assertStringContainsString("\$environment !== 'test'", $reset);
        self::assertStringContainsString('(?:_test|_ci)', $reset);
        self::assertStringNotContainsString('SET FOREIGN_KEY_CHECKS', $reset);
        self::assertStringNotContainsString('DROP DATABASE', $reset);
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

    private function sourceUnder(string $directory): string
    {
        $source = '';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $source .= $this->read($file->getPathname());
            }
        }

        return $source;
    }
}
