<?php

declare(strict_types=1);

namespace Qmdb\Tests\Tools\Closeout;

use PHPUnit\Framework\TestCase;
use Qmdb\Tools\Closeout\P2FreezeGenerator;
use Qmdb\Tools\Closeout\P2FreezePolicy;
use Qmdb\Tools\Support\GitMetadata;

final class P2FreezeTest extends TestCase
{
    public function testPolicyGovernsP2EvidenceAndExcludesGeneratedAndSelfReferentialFiles(): void
    {
        $policy = new P2FreezePolicy();

        self::assertSame(P2FreezePolicy::FROZEN, $policy->category('src/Modules/Identity/Application/RegisterAccount.php'));
        self::assertSame(P2FreezePolicy::EXTENSION, $policy->category('routes/web.php'));
        self::assertTrue($policy->isIncluded('docs/closeout/p2/01-executive-closeout-summary.md'));
        self::assertFalse($policy->isIncluded('build/release/qmdb.tar.gz'));
        self::assertFalse($policy->isIncluded('src/Modules/Geography/Domain/GeographyAreaType.php'));
        self::assertTrue($policy->isP3B01MutableExistingPath('src/Bootstrap/ApplicationFactory.php'));
        self::assertTrue($policy->isP3B01MutableExistingPath('src/Bootstrap/ApplicationMetadata.php'));
        self::assertTrue($policy->isP3B01MutableExistingPath('public/assets/js/app.js'));
        self::assertTrue($policy->isP3B01MutableExistingPath('tools/Build/ReleaseFilePolicy.php'));
        self::assertTrue($policy->isP3B01MutableExistingPath('tests/Tools/Build/ReleaseFilePolicyTest.php'));
        self::assertTrue($policy->isP3B01Extension('public/assets/js/geography-dependent-select.js'));
        self::assertTrue($policy->isP3B02Extension('src/Modules/People/Application/PersonProfileService.php'));
        self::assertTrue($policy->isP3B03Extension('src/Modules/Organizations/Application/OrganizationsRegistryService.php'));
        self::assertTrue($policy->isP3B04Extension('src/Modules/OrganizationAffiliations/Application/OrganizationAffiliationRequestService.php'));
        self::assertTrue($policy->isP3B04Extension('tests/Frontend/organization-affiliation-roster-controller.test.js'));
        self::assertTrue($policy->isP3B02MutableExistingPath('src/Modules/SecurityWeb/Csrf/CsrfAction.php'));
        self::assertTrue($policy->isP3B03MutableExistingPath('src/Shared/Schema/Migration/MigrationPlanner.php'));
        self::assertTrue($policy->isP3B04MutableExistingPath('src/Shared/Schema/State/MySqlSchemaStateRepository.php'));
        self::assertTrue($policy->isP3B04MutableExistingPath('routes/web.php'));
        self::assertTrue($policy->isP3B04MutableExistingPath('docs/project/project-state.md'));
        self::assertTrue($policy->isP3B04MutableExistingPath('tests/Integration/MySql/P2SecurityAuthorizationIntegrationTest.php'));
        self::assertTrue($policy->isP3B02MutableExistingPath('tests/Integration/MySql/P2IdentityRecoveryHttpIntegrationTest.php'));
        self::assertFalse($policy->isIncluded('docs/closeout/p2/qmdb-p2-identity-security-tenancy-freeze.yaml'));
    }

    public function testRenderedP2FreezeIsDeterministicAndPreservesTheP3AuthorizationBoundary(): void
    {
        $generator = new P2FreezeGenerator();
        $git = new GitMetadata(str_repeat('a', 40), str_repeat('a', 12), 1_700_000_000, 'clean');
        $entries = [[
            'path' => 'src/Modules/Identity/Application/RegisterAccount.php',
            'category' => P2FreezePolicy::FROZEN,
            'sha256' => str_repeat('b', 64),
        ]];
        $metrics = [
            'governed_files' => 1, 'governed_hash' => str_repeat('c', 64), 'modules' => 1,
            'module_hash' => str_repeat('d', 64), 'migrations' => 28, 'migration_hash' => str_repeat('e', 64),
            'seeds' => 3, 'seed_hash' => str_repeat('f', 64), 'tables' => 49, 'triggers' => 3,
            'foreign_keys' => 1, 'generated_columns' => 0, 'indexes' => 1, 'schema_hash' => str_repeat('1', 64),
            'routes' => 88, 'mutations' => 44, 'route_hash' => str_repeat('2', 64), 'permissions' => 32,
            'roles' => 9, 'mappings' => 83, 'privileged_policies' => 20, 'authorization_hash' => str_repeat('3', 64),
            'tasks' => 3, 'scheduler_hash' => str_repeat('4', 64), 'php_tests' => 939,
            'php_assertions' => 66596, 'mysql_tests' => 83, 'mysql_assertions' => 1659,
            'frontend_tests' => 51, 'release_revision' => str_repeat('5', 40),
            'release_artifact' => 'qmdb-0.1.0-dev-aaaaaaaaaaaa.tar.gz', 'archive_hash' => str_repeat('6', 64),
            'release_manifest_hash' => str_repeat('7', 64), 'sbom_hash' => str_repeat('8', 64),
        ];

        $first = $generator->render($entries, $metrics, $git);
        $second = $generator->render($entries, $metrics, $git);

        self::assertSame($first, $second);
        self::assertStringContainsString('freeze_id: QMDB-P2-FRZ-001', $first);
        self::assertStringContainsString('status: FROZEN', $first);
        self::assertStringContainsString('identifier: P3', $first);
        self::assertStringContainsString('status: NOT_STARTED_NOT_AUTHORIZED', $first);
    }

    public function testCiCountParsesPassingPhpAndMySqlEvidence(): void
    {
        $generator = new P2FreezeGenerator();
        $method = new \ReflectionMethod($generator, 'ciCount');
        $ci = [
            'steps' => [
                ['name' => 'php-tests', 'status' => 'passed', 'output' => 'OK (941 tests, 66747 assertions)'],
                ['name' => 'mysql-tests', 'status' => 'passed', 'output' => 'OK (83 tests, 1659 assertions)'],
            ],
        ];
        $pattern = '/OK \\((\\d+) tests, (\\d+) assertions\\)/';

        self::assertSame(941, $method->invoke($generator, $ci, 'php-tests', $pattern, 1));
        self::assertSame(66747, $method->invoke($generator, $ci, 'php-tests', $pattern, 2));
        self::assertSame(83, $method->invoke($generator, $ci, 'mysql-tests', $pattern, 1));
        self::assertSame(1659, $method->invoke($generator, $ci, 'mysql-tests', $pattern, 2));
    }

    public function testFreezeEntryPatternAcceptsTheP2FrozenCategory(): void
    {
        $entry = "    - path: \"src/Modules/Identity/Application/RegisterAccount.php\"\n"
            . "      category: FROZEN_P2_IDENTITY_SECURITY_TENANCY\n"
            . '      sha256: ' . str_repeat('a', 64);

        self::assertSame(1, preg_match(
            '/^\\s+- path: "([^"]+)"\\R\\s+category: ([A-Z0-9_]+)\\R\\s+sha256: ([a-f0-9]{64})\\s*$/m',
            $entry,
        ));
    }
}
