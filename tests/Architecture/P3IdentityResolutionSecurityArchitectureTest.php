<?php

declare(strict_types=1);

namespace Qmdb\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class P3IdentityResolutionSecurityArchitectureTest extends TestCase
{
    public function testIdentityResolutionIsARegisteredPrivateBoundary(): void
    {
        $root = dirname(__DIR__, 2);
        $module = (string) file_get_contents($root . '/src/Bootstrap/Module/PeopleIdentityResolutionModule.php');
        $controller = (string) file_get_contents($root . '/src/Modules/IdentityResolution/Interface/Http/PeopleIdentityResolutionController.php');
        $repository = (string) file_get_contents($root . '/src/Modules/IdentityResolution/Infrastructure/Persistence/MySqlIdentityResolutionRepository.php');

        self::assertStringContainsString('people.identity_resolution', $module);
        self::assertStringContainsString('AuthenticatedRequestGuard', $controller);
        self::assertStringContainsString('Cache-Control', $controller);
        self::assertStringContainsString('no-store', $controller);
        self::assertStringNotContainsString('localStorage', $controller);
        self::assertStringNotContainsString('person.search', strtolower($controller));
        self::assertStringContainsString('FOR UPDATE', $repository);
        self::assertStringContainsString('open_account_marker', (string) file_get_contents($root . '/src/Modules/IdentityResolution/Infrastructure/Migration/CreateProfileClaimPairingAndClaimMigration.php'));
        $fragment = (string) file_get_contents($root . '/resources/views/fragments/person-identity-resolution.php');
        self::assertStringContainsString("translator->trans('identity_resolution.'", $fragment);
        self::assertStringContainsString('data-qmdb-person-confirm', $fragment);
        self::assertStringContainsString('data-qmdb-person-confirm', (string) file_get_contents($root . '/public/assets/js/person-identity-resolution-controller.js'));
        self::assertStringContainsString("'identity_resolution.pairing_heading'", (string) file_get_contents($root . '/resources/translations/ar.php'));
    }

    public function testEveryIdentityResolutionMutationIsCsrfAndIdempotencyClassified(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $catalog = (string) file_get_contents($root . '/src/Shared/Http/Routing/Security/ProductionRouteSecurityPolicyCatalog.php');
        foreach (['profile_claim_pairing.create', 'profile_claim.accept', 'profile_verification.record', 'person_duplicate.review', 'person_duplicate.resolve.submit'] as $route) {
            self::assertStringContainsString($route, $routes);
            self::assertStringContainsString($route, $catalog);
        }
        self::assertStringContainsString('people.claim_pairing.create', $catalog);
        self::assertStringContainsString('people.duplicate.resolve', $catalog);
        self::assertStringContainsString('PROFILE_DUPLICATE_REVIEW', (string) file_get_contents($root . '/src/Modules/IdentityResolution/Infrastructure/Migration/ExtendIdentityResolutionReviewIdempotencyMigration.php'));
    }

    public function testPairingPersistenceIsHashOnlyAndUnknownPairingsUseTheSameGenericBoundary(): void
    {
        $root = dirname(__DIR__, 2);
        $migration = (string) file_get_contents($root . '/src/Modules/IdentityResolution/Infrastructure/Migration/CreateProfileClaimPairingAndClaimMigration.php');
        $repository = (string) file_get_contents($root . '/src/Modules/IdentityResolution/Infrastructure/Persistence/MySqlIdentityResolutionRepository.php');
        $hasher = (string) file_get_contents($root . '/src/Modules/IdentityResolution/Application/ProfileClaimPairingHasher.php');
        $resolver = (string) file_get_contents($root . '/src/Modules/IdentityResolution/Application/ProfileClaimPairingResolver.php');

        self::assertStringContainsString('secret_hash BINARY(32) NOT NULL', $migration);
        self::assertStringContainsString('hmac_key_version', $migration);
        self::assertStringContainsString(':hash', $repository);
        self::assertStringNotContainsString('$code->secret', $repository);
        self::assertStringContainsString("hash_hmac('sha256'", $hasher);
        self::assertStringContainsString('dummyCompare()', $resolver);
        self::assertGreaterThanOrEqual(2, substr_count($resolver, "Claim pairing is unavailable."));
    }

    public function testCanonicalizationUsesCallerOwnedParticipantsAndNoPersonDeletePath(): void
    {
        $root = dirname(__DIR__, 2);
        $resolution = (string) file_get_contents($root . '/src/Modules/IdentityResolution/Application/PersonDuplicateResolutionService.php');
        $peopleParticipant = (string) file_get_contents($root . '/src/Modules/People/Application/PersonCanonicalizationParticipant.php');
        $affiliationParticipant = (string) file_get_contents($root . '/src/Modules/OrganizationAffiliations/Application/OrganizationAffiliationPersonCanonicalizationParticipant.php');
        $repository = (string) file_get_contents($root . '/src/Modules/IdentityResolution/Infrastructure/Persistence/MySqlIdentityResolutionRepository.php');

        self::assertStringContainsString('$this->transactions->transactional', $resolution);
        self::assertStringContainsString('$this->people->apply', $resolution);
        self::assertStringContainsString('$this->affiliations->apply', $resolution);
        self::assertStringContainsString('retirePersonAndAlias', $resolution);
        self::assertStringContainsString('caller-owned active database transaction', $peopleParticipant);
        self::assertStringContainsString('active transaction owned by canonicalization orchestration', $affiliationParticipant);
        self::assertStringNotContainsString('DELETE FROM people_persons', $repository);
        self::assertStringContainsString('revokePendingClaimsForPerson', $resolution);
    }

    public function testPrivateControllerHasNoPublicDiscoveryOrPersistenceAuthority(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = (string) file_get_contents($root . '/src/Modules/IdentityResolution/Interface/Http/PeopleIdentityResolutionController.php');
        $routes = (string) file_get_contents($root . '/routes/web.php');

        self::assertStringContainsString('AuthenticatedRequestGuard', $controller);
        self::assertStringNotContainsString('PDO', $controller);
        self::assertStringNotContainsString('hash_hmac', $controller);
        self::assertStringNotContainsString('person.search', strtolower($routes));
        self::assertStringNotContainsString('birth_date_search', strtolower($routes));
        self::assertStringNotContainsString('public.person', strtolower($routes));
        self::assertStringContainsString('Cache-Control', $controller);
        self::assertStringContainsString('no-store', $controller);
    }

    public function testB05MigrationsAndSeedRemainExplicitlyRegisteredWithoutFuturePhaseSource(): void
    {
        $root = dirname(__DIR__, 2);
        $migrations = (string) file_get_contents($root . '/database/migrations.php');
        $seeds = (string) file_get_contents($root . '/database/seeds.php');
        $module = (string) file_get_contents($root . '/src/Bootstrap/Module/PeopleIdentityResolutionModule.php');

        foreach (['CreateProfileClaimPairingAndClaimMigration', 'CreateProfileVerificationAssertionsMigration', 'CreatePersonDuplicateCasesMigration', 'CreatePersonCanonicalAliasesMigration', 'ExtendIdentityResolutionSecurityCatalogMigration', 'ExtendIdentityResolutionReviewIdempotencyMigration'] as $migration) {
            self::assertStringContainsString($migration, $migrations);
        }
        self::assertStringContainsString('SeedPeopleIdentityResolutionAuthorization', $seeds);
        self::assertStringContainsString('people.identity_resolution', $module);
        self::assertDirectoryDoesNotExist($root . '/src/Modules/Competitions');
        self::assertFileDoesNotExist($root . '/src/Bootstrap/Module/PhaseFourModule.php');
    }

    public function testClaimAuthorizationAndAcceptanceRemainSeparateDualConsentWorkflows(): void
    {
        $root = dirname(__DIR__, 2);
        $creation = (string) file_get_contents($root . '/src/Modules/IdentityResolution/Application/ProfileClaimPairingCreationService.php');
        $guardian = (string) file_get_contents($root . '/src/Modules/IdentityResolution/Application/GuardianProfileClaimAuthorizationService.php');
        $platform = (string) file_get_contents($root . '/src/Modules/IdentityResolution/Application/PlatformProfileClaimAuthorizationService.php');
        $acceptance = (string) file_get_contents($root . '/src/Modules/IdentityResolution/Application/ProfileClaimAcceptanceService.php');

        self::assertStringContainsString('activeSelfLinkForAccount', $creation);
        self::assertStringContainsString('displayOnceCode', (string) file_get_contents($root . '/src/Modules/IdentityResolution/Application/ProfileClaimPairingCreationResult.php'));
        self::assertStringContainsString('guardianAuthority', $guardian);
        self::assertStringContainsString('PROFILE_CLAIM_AUTHORIZE_GUARDIAN', $guardian);
        self::assertStringContainsString('platform.people_profile_claims.authorize', $platform);
        self::assertStringContainsString('PROFILE_CLAIM_AUTHORIZE_PLATFORM', $platform);
        self::assertStringContainsString('ProfileClaimEligibility::adult', $guardian);
        self::assertStringContainsString('ProfileClaimEligibility::adult', $platform);
        self::assertStringContainsString('PROFILE_CLAIM_ACCEPT', $acceptance);
        self::assertStringContainsString('createSelfLink', $acceptance);
        self::assertStringContainsString('revokeActiveGuardianshipsForDependent', $acceptance);
        self::assertStringNotContainsString('workspaceMembership', $acceptance);
        self::assertStringNotContainsString('assignRole', $acceptance);
    }
}
