<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Modules\People;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Qmdb\Modules\People\Configuration\PeopleProfilesConfigurationFactory;
use Qmdb\Modules\People\Domain\PersonBirthDate;
use Qmdb\Modules\People\Domain\PersonName;
use Qmdb\Modules\People\Domain\PersonProfileAccessPolicy;
use Qmdb\Modules\People\Domain\PersonProfileAccessReason;
use Qmdb\Modules\People\Infrastructure\Security\SecurePersonRegistryCodeGenerator;
use Qmdb\Shared\Configuration\EnvironmentVariables;

final class PersonProfileFoundationTest extends TestCase
{
    public function testRegistryCodesUseTheApprovedNonSequentialOpaqueFormat(): void
    {
        $generator = new SecurePersonRegistryCodeGenerator();
        $codes = [];
        for ($index = 0; $index < 64; $index++) {
            $code = $generator->generate()->value();
            self::assertMatchesRegularExpression('/\AQMP-[A-HJKMNP-TV-Z2-9]{16}\z/', $code);
            $codes[$code] = true;
        }

        self::assertCount(64, $codes);
    }

    public function testNameNormalizationPreservesUnicodeAndRejectsUnsafeContent(): void
    {
        $name = new PersonName([
            'given_name' => '  Amina  ',
            'middle_names' => '  بنت   أحمد ',
            'family_name' => ' Yusuf ',
            'display_name' => '  Amina   يوسف ',
        ], 200);
        self::assertSame('Amina يوسف', $name->displayName());
        self::assertSame('Amina بنت أحمد', $name->givenName() . ' ' . $name->middleNames());
        self::assertSame('amina يوسف', $name->searchName(256));

        $this->expectException(InvalidArgumentException::class);
        new PersonName(['display_name' => '<script>alert(1)</script>'], 200);
    }

    public function testBirthDateAndAccessPolicyApplyAgeAndGuardianBoundaries(): void
    {
        $now = new DateTimeImmutable('2026-09-01T12:00:00+00:00');
        self::assertTrue(PersonBirthDate::fromString('2008-09-02', $now, 120)->isBelowAge(18, $now));
        self::assertFalse(PersonBirthDate::fromString('2008-09-01', $now, 120)->isBelowAge(18, $now));

        $policy = new PersonProfileAccessPolicy();
        self::assertSame(PersonProfileAccessReason::SELF_LINK_REQUIRED, $policy->self(null)->reason);
        self::assertSame(PersonProfileAccessReason::GUARDIAN_ROLE_REQUIRED, $policy->guardian(
            ['status' => 'ACTIVE'],
            null,
            ['status' => 'ACTIVE'],
        )->reason);
        self::assertTrue($policy->guardian(
            ['status' => 'ACTIVE'],
            ['status' => 'ACTIVE'],
            ['status' => 'ACTIVE'],
        )->allowed);
    }

    public function testConfigurationRejectsUnsafeProfileAndRateLimitValues(): void
    {
        $factory = new PeopleProfilesConfigurationFactory();
        $defaults = $factory->create(new EnvironmentVariables([]));
        self::assertSame(18, $defaults->minorThresholdYears);
        self::assertSame(30, $defaults->mutationMaximumAttempts);

        $this->expectException(InvalidArgumentException::class);
        $factory->create(new EnvironmentVariables(['PERSON_PROFILE_MAX_DEPENDENTS_PER_GUARDIAN' => '101']));
    }
}
