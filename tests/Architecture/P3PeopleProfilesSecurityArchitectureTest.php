<?php

declare(strict_types=1);

namespace Qmdb\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class P3PeopleProfilesSecurityArchitectureTest extends TestCase
{
    public function testPeopleProfileRoutesArePrivateAndNoPublicDiscoverySurfaceIsRegistered(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        preg_match_all("/'account\\.person_profile\\.[^']+'/", $routes, $matches);

        self::assertCount(20, $matches[0]);
        self::assertStringContainsString("'/account/profile'", $routes);
        self::assertStringNotContainsString("'/people'", $routes);
        self::assertStringNotContainsString('person.search', $routes);
        self::assertStringNotContainsString('person.merge', $routes);
    }

    public function testPrivatePresentationAndAuditsDoNotPersistOrExposeProfileFields(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = (string) file_get_contents($root . '/src/Modules/People/Interface/Http/PeopleProfilesController.php');
        $service = (string) file_get_contents($root . '/src/Modules/People/Application/PersonProfileService.php');
        $template = (string) file_get_contents($root . '/resources/views/fragments/account-person-profile.php');

        self::assertStringContainsString("'Cache-Control', 'private, no-store'", $controller);
        self::assertStringContainsString("'Referrer-Policy', 'no-referrer'", $controller);
        self::assertStringNotContainsString('display_name' . "' =>", $service);
        self::assertStringNotContainsString('birth_date' . "' =>", $service);
        self::assertStringNotContainsString('localStorage', $template);
        self::assertStringNotContainsString('sessionStorage', $template);
        self::assertStringNotContainsString('name="person_id"', $template);
    }

    public function testGeographySelectorIsBoundedAndUsesTheApprovedChildLookup(): void
    {
        $root = dirname(__DIR__, 2);
        $template = (string) file_get_contents($root . '/resources/views/fragments/account-person-profile.php');
        $lookup = (string) file_get_contents($root . '/src/Modules/Geography/Interface/Http/GeographyChildrenLookupController.php');

        self::assertStringContainsString('geography_level_one_areas', $template);
        self::assertStringContainsString('data-qmdb-geography-child-field="origin_level_two_area_public_id"', $template);
        self::assertStringContainsString('data-qmdb-geography-child-field="residence_level_two_area_public_id"', $template);
        self::assertStringContainsString("'origin_level_two_area_public_id'", $lookup);
        self::assertStringContainsString("'residence_level_two_area_public_id'", $lookup);
    }
}
