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
}
