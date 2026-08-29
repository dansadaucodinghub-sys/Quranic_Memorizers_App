<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;

final class P2TenantContextArchitectureTest extends TestCase
{
    public function testRoutesMiddlewareModuleAndMigrationAreExplicitlyRegistered(): void
    {
        $root = dirname(__DIR__, 3);
        $routes = (string)file_get_contents($root . '/routes/web.php');
        $paths = ['/account/workspaces', '/account/workspaces/switch', '/account/workspaces/clear', '/workspace'];
        foreach ($paths as $path) {
            self::assertStringContainsString($path, $routes);
        }
        $http = (string)file_get_contents($root . '/src/Bootstrap/Module/ApplicationHttpModule.php');
        self::assertGreaterThan(
            strpos($http, 'SessionAuthenticationMiddleware::class'),
            strpos($http, 'TenantContextMiddleware::class'),
        );
        $migrations = (string)file_get_contents($root . '/database/migrations.php');
        self::assertStringContainsString('AddSessionBoundTenantContextMigration', $migrations);
        $composer = (string)file_get_contents($root . '/composer.json');
        self::assertStringContainsString('tools/Ci/run-mysql-tests.php', $composer);
        $ci = (string)file_get_contents($root . '/tools/Ci/LocalCiRunner.php');
        $authorization = strpos($ci, "'mysql-authorization-verify'");
        $tenant = strpos($ci, "'mysql-tenant-context-verify'");
        $schema = strpos($ci, "'mysql-schema-verify'");
        self::assertIsInt($authorization);
        self::assertIsInt($tenant);
        self::assertIsInt($schema);
        self::assertGreaterThan($authorization, $tenant);
        self::assertGreaterThan($tenant, $schema);
    }

    public function testClientNeverPersistsWorkspaceAuthorityAndOnlyBroadcastsVersion(): void
    {
        $root = dirname(__DIR__, 3);
        $client = (string)file_get_contents($root . '/public/assets/js/tenant-context-controller.js');
        self::assertStringNotContainsString('localStorage', $client);
        self::assertStringNotContainsString('sessionStorage', $client);
        self::assertStringNotContainsString('workspace_id', $client);
        self::assertStringContainsString(
            'postMessage({ type: MESSAGE_TYPE, tenant_context_version: version })',
            $client,
        );
        self::assertStringContainsString("new BroadcastChannel('qmdb-tenant-context')", $client);
        self::assertStringContainsString('X-QMDB-Tenant-Context-Version', (string)file_get_contents(
            $root . '/public/assets/js/mutation-fetch-client.js',
        ));
    }

    public function testTenantScopedContractsCoverRepositoriesJobsCachesAndExports(): void
    {
        $root = dirname(__DIR__, 3);
        self::assertStringNotContainsString('extends TenantScopedRepository', (string)file_get_contents(
            $root . '/src/Modules/TenancyContext/Domain/Repository/SessionTenantContextRepository.php',
        ));
        foreach (
            [
                '/src/Modules/Tenancy/Domain/Repository/WorkspaceMembershipRepository.php',
                '/src/Modules/SecurityAuthorization/Domain/Repository/WorkspaceRoleAssignmentRepository.php',
            ] as $repository
        ) {
            self::assertStringContainsString('extends TenantScopedRepository', (string)file_get_contents(
                $root . $repository,
            ));
        }
        self::assertFileExists(
            $root . '/src/Modules/TenancyContext/Application/Background/AccountTenantBoundBackgroundJob.php',
        );
        self::assertFileExists(
            $root . '/src/Modules/TenancyContext/Application/Background/TenantBoundBackgroundJobContextResolver.php',
        );
        self::assertFileExists(
            $root . '/src/Modules/TenancyContext/Application/Cache/TenantScopedCacheKeyFactory.php',
        );
        self::assertFileExists(
            $root . '/src/Modules/TenancyContext/Application/Export/TenantScopedExportRequest.php',
        );
        self::assertStringContainsString('AccountWorkspaceTenantContext', (string)file_get_contents(
            $root . '/src/Modules/SecurityAuthorization/Domain/WorkspaceAuthorizationScope.php',
        ));
        self::assertFileExists(
            $root . '/src/Modules/TenancyContext/Application/AccountWorkspaceInventoryQuery.php',
        );
    }
}
