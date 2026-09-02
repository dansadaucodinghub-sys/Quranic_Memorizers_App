<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Modules\OrganizationAffiliations;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\OrganizationAffiliations\Application\OrganizationAffiliationInput;
use Qmdb\Modules\OrganizationAffiliations\Configuration\OrganizationAffiliationsConfigurationFactory;
use Qmdb\Modules\OrganizationAffiliations\Domain\OrganizationAffiliationStatus;
use Qmdb\Modules\OrganizationAffiliations\Infrastructure\Security\SecureOrganizationAffiliationCodeGenerator;
use Qmdb\Shared\Configuration\EnvironmentVariables;

final class OrganizationAffiliationFoundationTest extends TestCase
{
    public function testConfigurationCodeGenerationAndAssignmentInputAreBounded(): void
    {
        $configuration = (new OrganizationAffiliationsConfigurationFactory())->create(new EnvironmentVariables([]));
        $code = (new SecureOrganizationAffiliationCodeGenerator())->generate();
        $input = OrganizationAffiliationInput::fromBody([
            'roles' => [[
                'code' => 'TEACHER',
                'is_primary' => true,
                'unit_public_id' => '01a0474d-b404-7100-8000-000000000001',
                'title' => '  Halaqah teacher  ',
            ]],
            'units' => [[
                'public_id' => '01a0474d-b404-7100-8000-000000000001',
                'is_primary' => true,
            ]],
        ], $configuration);

        self::assertSame(2_592_000, $configuration->requestTtlSeconds);
        self::assertSame(80, $configuration->codeEntropyBits);
        self::assertMatchesRegularExpression('/\AQMA-[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{16}\z/', $code);
        self::assertSame('Halaqah teacher', $input->roles[0]['title']);
    }

    public function testStatusTransitionsAndAssignmentPrimaryInvariantFailClosed(): void
    {
        self::assertTrue(OrganizationAffiliationStatus::PENDING_ACCEPTANCE->permits(OrganizationAffiliationStatus::ACTIVE));
        self::assertFalse(OrganizationAffiliationStatus::ENDED->permits(OrganizationAffiliationStatus::ACTIVE));
        $configuration = (new OrganizationAffiliationsConfigurationFactory())->create(new EnvironmentVariables([]));

        $this->expectException(\InvalidArgumentException::class);
        OrganizationAffiliationInput::fromBody([
            'roles' => [[
                'code' => 'TEACHER',
                'is_primary' => false,
                'unit_public_id' => null,
                'title' => null,
            ]],
            'units' => [],
        ], $configuration);
    }

    public function testMultipleRolesAndUnitsAllowExactlyOnePrimaryWithoutDuplicateScope(): void
    {
        $configuration = (new OrganizationAffiliationsConfigurationFactory())->create(new EnvironmentVariables([]));
        $input = OrganizationAffiliationInput::fromBody([
            'roles' => [
                ['code' => 'TEACHER', 'is_primary' => true, 'unit_public_id' => '01a0474d-b404-7100-8000-000000000001', 'title' => null],
                ['code' => 'RECITER', 'is_primary' => false, 'unit_public_id' => null, 'title' => null],
            ],
            'units' => [
                ['public_id' => '01a0474d-b404-7100-8000-000000000001', 'is_primary' => true],
                ['public_id' => '01a0474d-b404-7100-8000-000000000002', 'is_primary' => false],
            ],
        ], $configuration);

        self::assertCount(2, $input->roles);
        self::assertCount(2, $input->units);
        self::assertSame('RECITER', $input->roles[1]['code']);
    }
}
