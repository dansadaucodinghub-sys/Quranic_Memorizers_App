<?php

declare(strict_types=1);

namespace Qmdb\Tests\Architecture;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Qmdb\Bootstrap\Security\P3PersonRepositorySecurityVerifier;

#[Group('P3SecurityHardening')]
#[Group('GeographyIntegrity')]
#[Group('PersonAuthority')]
#[Group('GuardianAuthority')]
#[Group('UnicodeSecurity')]
#[Group('OrganizationTenantIsolation')]
#[Group('OrganizationHierarchy')]
#[Group('AffiliationConsentSecurity')]
#[Group('LeadershipSecurity')]
#[Group('ClaimPairingSecurity')]
#[Group('ProfileClaimSecurity')]
#[Group('DuplicateConsentSecurity')]
#[Group('CanonicalPersonIntegrity')]
#[Group('P3RepositoryScopes')]
#[Group('P3RouteSecurity')]
#[Group('P3InputFuzz')]
#[Group('P3FaultInjection')]
#[Group('P3Concurrency')]
#[Group('P3SecurityPerformance')]
final class P3SecurityHardeningArchitectureTest extends TestCase
{
    public function testP3VerifierIsARegisteredBoundedReadOnlyComposition(): void
    {
        $root = dirname(__DIR__, 2);
        $module = (string) file_get_contents($root . '/src/Bootstrap/Module/ConsoleFoundationModule.php');
        $command = (string) file_get_contents($root . '/src/Bootstrap/Console/P3SecurityHardeningVerifyConsoleCommand.php');
        $personCommand = (string) file_get_contents($root . '/src/Bootstrap/Console/P3PersonRepositorySecurityVerifyConsoleCommand.php');
        $verifier = (string) file_get_contents($root . '/src/Bootstrap/Security/P3SecurityHardeningVerifier.php');

        self::assertStringContainsString('P3SecurityHardeningVerifyConsoleCommand', $module);
        self::assertStringContainsString("security:p3:verify", $command);
        self::assertStringContainsString("security:person-repositories:verify", $personCommand);
        self::assertStringContainsString('P2SecurityHardeningVerifier', $verifier);
        self::assertStringContainsString('GeographyReferenceReadinessCheck', $verifier);
        self::assertStringContainsString('PeopleProfilesReadinessCheck', $verifier);
        self::assertStringContainsString('OrganizationsRegistryReadinessCheck', $verifier);
        self::assertStringContainsString('OrganizationAffiliationsReadinessCheck', $verifier);
        self::assertStringContainsString('PeopleIdentityResolutionReadinessCheck', $verifier);
        self::assertStringContainsString('P3PersonRepositorySecurityVerifier', $verifier);
        self::assertStringNotContainsString('transactional(', $verifier);
        self::assertStringNotContainsString('PDO', $verifier);
    }

    public function testPersonRepositoryScopeVerifierRejectsPublicIdOnlyAuthority(): void
    {
        $report = (new P3PersonRepositorySecurityVerifier(dirname(__DIR__, 2)))->verify();

        self::assertTrue($report->isValid(), implode(', ', $report->errors));
        self::assertSame(1, $report->repositoryCount);
        self::assertSame(5, $report->scopeCheckCount);
    }

    public function testP3BoundariesRetainNoPublicDiscoveryOrFuturePhaseProductionSurface(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');

        self::assertStringNotContainsString("new Route('public.person", $routes);
        self::assertStringNotContainsString('person.search', strtolower($routes));
        self::assertStringNotContainsString('organization.roster.public', strtolower($routes));
        self::assertDirectoryDoesNotExist($root . '/src/Modules/Competitions');
        self::assertDirectoryDoesNotExist($root . '/src/Modules/PhaseFour');
        self::assertFileDoesNotExist($root . '/docs/closeout/p3/qmdb-p3-freeze.yaml');
    }
}
