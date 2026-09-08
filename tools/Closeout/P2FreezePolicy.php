<?php

declare(strict_types=1);

namespace Qmdb\Tools\Closeout;

use Qmdb\Tools\Policy\PathPolicy;
use Qmdb\Tools\Support\FileSystem;

/**
 * Defines the P2 identity, security and tenant-isolation freeze boundary.
 * The policy intentionally permits only explicit controlled extension points.
 */
final class P2FreezePolicy
{
    public const FROZEN = 'FROZEN_P2_IDENTITY_SECURITY_TENANCY';
    public const EXTENSION = 'CONTROLLED_EXTENSION_POINT';

    /** @var list<string> */
    private const ROOT_FILES = [
        '.editorconfig', '.env.example', '.gitattributes', '.gitignore', '.npmrc', '.nvmrc', 'README.md',
        'compose.mysql-test.yaml', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json',
        'phpcs.xml.dist', 'phpstan.neon.dist', 'phpunit.xml.dist',
    ];

    /** @var list<string> */
    private const SOURCE_PREFIXES = [
        '.github/', 'bin/', 'config/', 'database/', 'public/', 'resources/', 'routes/', 'scripts/', 'src/', 'tests/', 'tools/',
    ];

    /** @var list<string> */
    private const GENERATED_PREFIXES = [
        '.git/', '.runtime/', '.phpstan.cache/', 'build/', 'coverage/', 'node_modules/', 'vendor/',
    ];

    /** @var list<string> */
    private const P3_B01_EXTENSION_PREFIXES = [
        'src/Modules/Geography/',
        'src/Bootstrap/Module/GeographyReferenceModule.php',
        'database/reference/nigeria-administrative-areas-v1.json',
        'resources/views/components/geography-',
        'resources/views/fragments/geography-',
        'resources/views/pages/nigeria-',
        'public/assets/js/geography-',
        'tests/Geography/',
        'tests/Unit/Modules/Geography/',
        'tests/Integration/MySql/Geography',
        'tests/Architecture/Geography',
        'tests/Frontend/geography-',
    ];

    /** @var list<string> */
    private const P3_B01_MUTABLE_EXISTING_PATHS = [
        'src/Bootstrap/ApplicationFactory.php',
        'src/Bootstrap/ApplicationMetadata.php',
        'src/Bootstrap/Module/ApplicationHttpModule.php',
        'src/Bootstrap/Module/ConsoleFoundationModule.php',
        'src/Bootstrap/Module/PresentationFoundationModule.php',
        'src/Modules/IdentitySessions/Interface/Http/ApplicationReadinessController.php',
        'src/Shared/Http/Routing/Security/ProductionRouteSecurityPolicyCatalog.php',
        'public/assets/js/app.js',
        'tools/Build/ReleaseFilePolicy.php',
        'tests/Tools/Build/ReleaseFilePolicyTest.php',
    ];

    /** @var list<string> */
    private const P3_B02_EXTENSION_PREFIXES = [
        'src/Modules/People/',
        'src/Bootstrap/Module/PeopleProfilesModule.php',
        'resources/views/pages/account-person-profile.php',
        'resources/views/fragments/account-person-profile.php',
        'tests/Unit/Modules/People/',
        'tests/Integration/MySql/P3People',
        'tests/Architecture/P3People',
        'tests/Support/MySql/P3People',
    ];

    /** @var list<string> */
    private const P3_B03_EXTENSION_PREFIXES = [
        'src/Modules/Organizations/',
        'src/Modules/SecurityAuthorization/Domain/OrganizationsAuthorizationCatalog.php',
        'src/Bootstrap/Module/OrganizationsRegistryModule.php',
        'tests/Unit/Modules/Organizations/',
        'tests/Integration/MySql/P3Organizations',
        'tests/Architecture/P3Organizations',
        'tests/Support/MySql/P3Organization',
    ];

    /** @var list<string> */
    private const P3_B04_EXTENSION_PREFIXES = [
        'src/Modules/OrganizationAffiliations/',
        'src/Bootstrap/Module/OrganizationsAffiliationsModule.php',
        'resources/views/pages/workspace-organization-affiliation-',
        'resources/views/pages/account-affiliation',
        'resources/views/pages/account-dependent-affiliation',
        'resources/views/fragments/organization-affiliation-',
        'resources/views/fragments/account-affiliation-',
        'resources/views/fragments/affiliation-',
        'public/assets/js/organization-affiliation-',
        'tests/Unit/Modules/OrganizationAffiliations/',
        'tests/Integration/MySql/P3OrganizationAffiliation',
        'tests/Architecture/P3OrganizationAffiliation',
        'tests/Support/MySql/P3OrganizationAffiliation',
        'tests/Frontend/organization-affiliation-',
    ];

    /** @var list<string> */
    private const P3_B05_EXTENSION_PREFIXES = [
        'src/Modules/IdentityResolution/',
        'src/Bootstrap/Module/PeopleIdentityResolutionModule.php',
        'src/Modules/SecurityAuthorization/Domain/PeopleIdentityResolutionAuthorizationCatalog.php',
        'src/Modules/People/Application/PersonCanonical',
        'src/Modules/People/Application/PersonManagementAuthoritySnapshotProvider.php',
        'src/Modules/People/Application/PersonVerificationProjectionProvider.php',
        'src/Modules/OrganizationAffiliations/Application/OrganizationAffiliationPersonCanonicalizationParticipant.php',
        'resources/views/pages/account-profile-claim',
        'resources/views/pages/account-dependent-profile-claim-',
        'resources/views/pages/account-person-duplicate-',
        'resources/views/pages/platform-profile-',
        'resources/views/pages/platform-person-duplicate-',
        'resources/views/fragments/person-identity-resolution.php',
        'public/assets/js/person-identity-resolution-',
        'tests/Unit/Modules/IdentityResolution/',
        'tests/Integration/MySql/P3IdentityResolution',
        'tests/Architecture/P3IdentityResolution',
        'tests/Frontend/person-identity-resolution-',
        'tests/Support/MySql/P3IdentityResolution',
        'docs/implementation/P3-B05-',
        'docs/implementation/reports/QMDB-P3-B05-',
        'docs/implementation/profile-claims-verification-consent-and-duplicate-resolution-standard.md',
        'docs/implementation/P3-requirements-to-batches.md',
        'docs/project/p3-p0-b05-freeze-extension-ledger.yaml',
    ];

    /** @var list<string> */
    private const P3_B06_EXTENSION_PREFIXES = [
        'src/Bootstrap/Security/P3',
        'src/Bootstrap/Console/P3SecurityHardeningVerifyConsoleCommand.php',
        'src/Bootstrap/Console/P3PersonRepositorySecurityVerifyConsoleCommand.php',
        'tests/Architecture/P3SecurityHardening',
        'tests/Security/P3',
        'docs/security/P3-',
        'docs/operations/P3-security-performance-baseline.md',
        'docs/implementation/reports/QMDB-P3-B06-',
        'docs/implementation/people-geography-and-organization-security-hardening-standard.md',
        'docs/project/p3-p0-b06-freeze-extension-ledger.yaml',
    ];

    /** @var list<string> */
    private const P3_CLOSE_EXTENSION_PREFIXES = [
        'tools/Closeout/P3Freeze',
        'tools/ci/generate-p3-freeze.php',
        'tools/ci/verify-p3-freeze.php',
        'docs/closeout/p3/',
    ];

    /** @var list<string> */
    private const P3_B02_MUTABLE_EXISTING_PATHS = [
        'src/Modules/IdentityAccess/Domain/IdempotencyOperation.php',
        'src/Modules/IdentityAccess/Infrastructure/Persistence/MySqlIdentityAccessRepository.php',
        'src/Modules/IdentityAccess/Security/RateLimit/IdentityRateLimitScope.php',
        'src/Modules/IdentityMultiFactor/Domain/StepUpAction.php',
        'src/Modules/IdentitySecurityNotifications/Domain/AccountSecurityNotificationType.php',
        'src/Modules/SecurityAudit/Domain/SecurityEventCode.php',
        'src/Modules/SecurityAudit/Domain/SecurityEventSubjectKind.php',
        'src/Modules/SecurityWeb/Csrf/CsrfAction.php',
        'src/Shared/Http/Routing/Security/ProductionRouteSecurityPolicyCatalog.php',
        'tests/Integration/MySql/GeographyReferenceIntegrationTest.php',
        'tests/Integration/MySql/IdentityTenancyFoundationIntegrationTest.php',
        'tests/Integration/MySql/P2IdentityAccessHttpIntegrationTest.php',
        'tests/Integration/MySql/P2IdentityMultiFactorIntegrationTest.php',
        'tests/Integration/MySql/P2IdentityRecoveryHttpIntegrationTest.php',
        'tests/Integration/MySql/P2WebAuthnCeremonyIntegrationTest.php',
    ];

    /** @var list<string> */
    private const P3_B03_MUTABLE_EXISTING_PATHS = [
        'src/Bootstrap/Module/SecurityAuthorizationModule.php',
        'src/Modules/SecurityAuthorization/Domain/AuthorizationCatalogRegistry.php',
        'src/Shared/Http/Routing/Security/RouteSecurityVerifier.php',
        'src/Shared/Schema/Migration/MigrationPlanner.php',
        'src/Shared/Schema/Seed/SeedPlanner.php',
    ];

    /** @var list<string> */
    private const P3_B04_MUTABLE_EXISTING_PATHS = [
        'resources/translations/ar.php',
        'resources/translations/en.php',
        'routes/web.php',
        'public/assets/js/app.js',
        'public/assets/js/mutation-fetch-client.js',
        'src/Modules/IdentitySessions/Interface/Http/ApplicationReadinessController.php',
        'src/Shared/Schema/State/MySqlSchemaStateRepository.php',
        'tests/Architecture/P2RouteSecurityPolicyTest.php',
        'tests/Architecture/SchemaFoundationArchitectureTest.php',
        'tests/Integration/Bootstrap/FoundationCompilationTest.php',
        'tests/Integration/MySql/P2SecurityAuthorizationIntegrationTest.php',
        'tests/Support/MySql/AuthorizationMySqlFixture.php',
        'docs/project/project-state.md',
    ];

    /** @var list<string> */
    private const P3_B05_MUTABLE_EXISTING_PATHS = [
        '.env.example',
        'phpunit.xml.dist',
        'resources/translations/ar.php',
        'resources/translations/en.php',
        'database/migrations.php',
        'database/seeds.php',
        'routes/web.php',
        'public/assets/js/app.js',
        'public/assets/js/mutation-fetch-client.js',
        'src/Bootstrap/ApplicationFactory.php',
        'src/Bootstrap/Module/ApplicationHttpModule.php',
        'src/Bootstrap/Module/ConsoleFoundationModule.php',
        'src/Bootstrap/Module/OrganizationsAffiliationsModule.php',
        'src/Bootstrap/Module/SecurityAuthorizationModule.php',
        'src/Modules/IdentityAccess/Infrastructure/Persistence/MySqlIdentityAccessRepository.php',
        'src/Modules/IdentityAccess/Security/RateLimit/IdentityRateLimitScope.php',
        'src/Modules/IdentityMultiFactor/Domain/StepUpAction.php',
        'src/Modules/IdentitySecurityNotifications/Domain/AccountSecurityNotificationType.php',
        'src/Modules/IdentitySessions/Interface/Http/ApplicationReadinessController.php',
        'src/Modules/SecurityAudit/Domain/SecurityEventCode.php',
        'src/Modules/SecurityAudit/Domain/SecurityEventSubjectKind.php',
        'src/Modules/SecurityAuthorization/Domain/AuthorizationCatalogRegistry.php',
        'src/Modules/SecurityWeb/Csrf/CsrfAction.php',
        'src/Shared/Http/Routing/Security/ProductionRouteSecurityPolicyCatalog.php',
        'src/Shared/Http/Routing/Security/RouteSecurityVerifier.php',
        'tests/Architecture/P2RouteSecurityPolicyTest.php',
        'tests/Architecture/ConfigurationArchitectureTest.php',
        'tests/Architecture/DatabaseArchitectureTest.php',
        'tests/Architecture/SchemaFoundationArchitectureTest.php',
        'tests/Architecture/SourceArchitectureTest.php',
        'tests/Integration/Console/BackgroundConsoleIntegrationTest.php',
        'tests/Integration/Bootstrap/FoundationCompilationTest.php',
        'tests/Frontend/test-dom.js',
        'tests/Frontend/mutation-fetch-client.test.js',
        'tests/Integration/MySql/IdentityTenancyFoundationIntegrationTest.php',
        'tests/Integration/MySql/P2IdentityMultiFactorIntegrationTest.php',
        'tests/Integration/MySql/P2IdentityRecoveryHttpIntegrationTest.php',
        'tests/Integration/MySql/P2SecurityAuthorizationIntegrationTest.php',
        'tests/Integration/MySql/P2WebAuthnCeremonyIntegrationTest.php',
        'tests/Support/MySql/AuthorizationMySqlFixture.php',
        'tests/Tools/Closeout/P2FreezeTest.php',
        'tests/Tools/Ci/FrozenBaselineVerifierTest.php',
        'tools/Ci/FrozenBaselineVerifier.php',
        'tools/Closeout/P2FreezePolicy.php',
        'tools/Closeout/P2FreezeVerifier.php',
        'docs/operations/quality-attribute-parameter-register.md',
        'README.md',
        'docs/implementation/phase-and-batch-roadmap.md',
        'docs/implementation/person-memorizer-reciter-competitor-and-guardian-profile-standard.md',
        'docs/implementation/organization-memberships-staff-leadership-and-person-affiliations-standard.md',
        'docs/data/mysql-logical-schema.yaml',
        'docs/data/02-domain-aggregate-and-ownership-model.md',
        'docs/data/05-table-and-column-data-dictionary.md',
        'docs/data/06-relationship-constraint-and-integrity-catalog.md',
        'docs/data/07-indexing-and-query-access-patterns.md',
        'docs/data/08-record-versioning-lifecycle-and-deletion.md',
        'docs/data/10-data-classification-ownership-and-lineage.md',
        'docs/security/security-control-catalog.md',
        'docs/security/security-verification-matrix.md',
        'docs/security/threat-model.md',
        'docs/privacy/data-classification-and-handling.md',
        'docs/accessibility/accessibility-verification-matrix.md',
        'docs/implementation/frontend-interaction-standard.md',
        'docs/implementation/presentation-and-progressive-interaction-standard.md',
        'docs/implementation/roles-permissions-and-scoped-authorization-standard.md',
        'docs/implementation/security-events-audit-integrity-and-account-state-standard.md',
        'docs/implementation/background-execution-standard.md',
        'docs/implementation/ci-build-and-release-standard.md',
        'docs/project/decision-register.md',
        'docs/project/open-decisions.md',
        'docs/project/risk-register.md',
        'docs/project/project-state.md',
        'docs/project/p3-p2-freeze-extension-ledger.yaml',
    ];

    /** @var list<string> */
    private const P3_B06_MUTABLE_EXISTING_PATHS = [
        'README.md',
        'src/Bootstrap/ApplicationMetadata.php',
        'src/Bootstrap/Module/ConsoleFoundationModule.php',
        'tests/Architecture/P3IdentityResolutionSecurityArchitectureTest.php',
        'tests/Unit/ApplicationMetadataTest.php',
        'tests/Unit/Shared/Application/SystemInformationTest.php',
        'tests/Integration/ConsoleApplicationTest.php',
        'tests/Integration/Http/HttpRoutesTest.php',
        'tests/Integration/Bootstrap/FoundationCompilationTest.php',
        'tests/Integration/Bootstrap/ApplicationFactoryTest.php',
        'tests/Integration/Console/BackgroundConsoleIntegrationTest.php',
        'tools/Closeout/P2FreezePolicy.php',
        'tools/Closeout/P2FreezeVerifier.php',
        'tools/Ci/FrozenBaselineVerifier.php',
        'tests/Tools/Closeout/P2FreezeTest.php',
        'tests/Tools/Ci/FrozenBaselineVerifierTest.php',
        'docs/implementation/P3-requirements-to-batches.md',
        'docs/implementation/phase-and-batch-roadmap.md',
        'docs/implementation/nigerian-administrative-geography-and-jurisdiction-standard.md',
        'docs/implementation/person-memorizer-reciter-competitor-and-guardian-profile-standard.md',
        'docs/implementation/organizations-schools-groups-mosques-and-branches-standard.md',
        'docs/implementation/organization-memberships-staff-leadership-and-person-affiliations-standard.md',
        'docs/implementation/profile-claims-verification-consent-and-duplicate-resolution-standard.md',
        'docs/data/mysql-logical-schema.yaml',
        'docs/data/02-domain-aggregate-and-ownership-model.md',
        'docs/data/05-table-and-column-data-dictionary.md',
        'docs/data/06-relationship-constraint-and-integrity-catalog.md',
        'docs/data/07-indexing-and-query-access-patterns.md',
        'docs/data/08-record-versioning-lifecycle-and-deletion.md',
        'docs/data/09-tenant-isolation-data-model.md',
        'docs/data/10-data-classification-ownership-and-lineage.md',
        'docs/security/security-control-catalog.md',
        'docs/security/security-verification-matrix.md',
        'docs/security/threat-model.md',
        'docs/privacy/data-classification-and-handling.md',
        'docs/accessibility/accessibility-verification-matrix.md',
        'docs/implementation/frontend-interaction-standard.md',
        'docs/implementation/presentation-and-progressive-interaction-standard.md',
        'docs/implementation/ci-build-and-release-standard.md',
        'docs/operations/quality-attribute-parameter-register.md',
        'docs/project/decision-register.md',
        'docs/project/open-decisions.md',
        'docs/project/risk-register.md',
        'docs/project/project-state.md',
        'docs/project/p3-p2-freeze-extension-ledger.yaml',
    ];

    public function __construct(private readonly PathPolicy $pathPolicy = new PathPolicy())
    {
    }

    /** @return list<array{path: string, category: string, sha256: string}> */
    public function entries(string $root): array
    {
        $entries = [];
        foreach (FileSystem::files($root) as $absolute) {
            $path = str_replace('\\', '/', FileSystem::relative($root, $absolute));
            if (!$this->isIncluded($path)) {
                continue;
            }
            if (is_link($absolute)) {
                throw new \RuntimeException('P2 freeze rejects symlink: ' . $path);
            }
            $violation = $this->pathPolicy->repositoryViolation($path);
            if ($violation !== null) {
                throw new \RuntimeException($violation);
            }
            $hash = hash_file('sha256', $absolute);
            if (!is_string($hash)) {
                throw new \RuntimeException('Unable to hash P2 governed file: ' . $path);
            }
            $entries[] = ['path' => $path, 'category' => $this->category($path), 'sha256' => $hash];
        }
        usort($entries, static fn (array $left, array $right): int => strcmp($left['path'], $right['path']));

        return $entries;
    }

    public function isIncluded(string $path): bool
    {
        $path = PathPolicy::normalize($path);
        if ($this->isP3AuthorizedExtension($path)) {
            return false;
        }
        if ($path === 'docs/closeout/p2/qmdb-p2-identity-security-tenancy-freeze.yaml') {
            return false;
        }
        foreach (self::GENERATED_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return false;
            }
        }
        if (in_array($path, self::ROOT_FILES, true)) {
            return true;
        }
        foreach (self::SOURCE_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }
        if (str_starts_with($path, 'docs/closeout/p2/')) {
            return true;
        }
        if (preg_match('#^docs/implementation/(?:P2-|identity-|account-|mfa-|roles-|tenant-|temporary-|security-events-|frontend-|presentation-|background-|ci-build-)#', $path) === 1) {
            return true;
        }
        if (preg_match('#^docs/implementation/reports/QMDB-P2-#', $path) === 1) {
            return true;
        }
        if (preg_match('#^docs/security/P2-#', $path) === 1 || $path === 'docs/operations/P2-security-performance-baseline.md') {
            return true;
        }

        return in_array($path, [
            'docs/project/project-state.md',
            'docs/project/decision-register.md',
            'docs/project/open-decisions.md',
            'docs/project/risk-register.md',
            'docs/implementation/requirements-to-implementation-map.md',
            'docs/implementation/definition-of-ready-and-done.md',
        ], true);
    }

    public function isP3B01Extension(string $path): bool
    {
        foreach (self::P3_B01_EXTENSION_PREFIXES as $prefix) {
            if (str_starts_with(PathPolicy::normalize($path), $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function isP3B02Extension(string $path): bool
    {
        foreach (self::P3_B02_EXTENSION_PREFIXES as $prefix) {
            if (str_starts_with(PathPolicy::normalize($path), $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function isP3AuthorizedExtension(string $path): bool
    {
        return $this->isP3B01Extension($path) || $this->isP3B02Extension($path) || $this->isP3B03Extension($path) || $this->isP3B04Extension($path) || $this->isP3B05Extension($path) || $this->isP3B06Extension($path) || $this->isP3CloseExtension($path);
    }

    public function isP3B01MutableExistingPath(string $path): bool
    {
        return in_array(PathPolicy::normalize($path), self::P3_B01_MUTABLE_EXISTING_PATHS, true);
    }

    public function isP3B02MutableExistingPath(string $path): bool
    {
        return in_array(PathPolicy::normalize($path), self::P3_B02_MUTABLE_EXISTING_PATHS, true);
    }

    public function isP3B03Extension(string $path): bool
    {
        foreach (self::P3_B03_EXTENSION_PREFIXES as $prefix) {
            if (str_starts_with(PathPolicy::normalize($path), $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function isP3B03MutableExistingPath(string $path): bool
    {
        return in_array(PathPolicy::normalize($path), self::P3_B03_MUTABLE_EXISTING_PATHS, true);
    }

    public function isP3B04Extension(string $path): bool
    {
        foreach (self::P3_B04_EXTENSION_PREFIXES as $prefix) {
            if (str_starts_with(PathPolicy::normalize($path), $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function isP3B04MutableExistingPath(string $path): bool
    {
        return in_array(PathPolicy::normalize($path), self::P3_B04_MUTABLE_EXISTING_PATHS, true);
    }

    public function isP3B05Extension(string $path): bool
    {
        foreach (self::P3_B05_EXTENSION_PREFIXES as $prefix) {
            if (str_starts_with(PathPolicy::normalize($path), $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function isP3B05MutableExistingPath(string $path): bool
    {
        return in_array(PathPolicy::normalize($path), self::P3_B05_MUTABLE_EXISTING_PATHS, true);
    }

    public function isP3B06Extension(string $path): bool
    {
        foreach (self::P3_B06_EXTENSION_PREFIXES as $prefix) {
            if (str_starts_with(PathPolicy::normalize($path), $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function isP3B06MutableExistingPath(string $path): bool
    {
        return in_array(PathPolicy::normalize($path), self::P3_B06_MUTABLE_EXISTING_PATHS, true);
    }

    public function isP3CloseExtension(string $path): bool
    {
        foreach (self::P3_CLOSE_EXTENSION_PREFIXES as $prefix) {
            if (str_starts_with(PathPolicy::normalize($path), $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function isP3MutableExistingPath(string $path): bool
    {
        return $this->isP3B01MutableExistingPath($path)
            || $this->isP3B02MutableExistingPath($path)
            || $this->isP3B03MutableExistingPath($path)
            || $this->isP3B04MutableExistingPath($path)
            || $this->isP3B05MutableExistingPath($path)
            || $this->isP3B06MutableExistingPath($path);
    }

    public function category(string $path): string
    {
        $path = PathPolicy::normalize($path);
        if (
            in_array($path, self::ROOT_FILES, true)
            || str_starts_with($path, '.github/')
            || str_starts_with($path, 'database/')
            || str_starts_with($path, 'docs/')
            || str_starts_with($path, 'resources/')
            || str_starts_with($path, 'routes/')
            || str_starts_with($path, 'tests/')
            || str_starts_with($path, 'tools/')
        ) {
            return self::EXTENSION;
        }

        return self::FROZEN;
    }
}
