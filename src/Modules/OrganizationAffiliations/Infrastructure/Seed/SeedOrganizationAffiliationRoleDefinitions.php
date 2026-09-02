<?php

declare(strict_types=1);

namespace Qmdb\Modules\OrganizationAffiliations\Infrastructure\Seed;

use Qmdb\Shared\Schema\Seed\Seed;
use Qmdb\Shared\Schema\Seed\SeedId;
use Qmdb\Shared\Schema\Seed\SeedStepId;
use Qmdb\Shared\Schema\Seed\SqlSeedStep;

final readonly class SeedOrganizationAffiliationRoleDefinitions implements Seed
{
    public function id(): SeedId
    {
        return new SeedId('20260901040300_seed_organization_affiliation_role_definitions');
    }
    public function description(): string
    {
        return 'Seed fixed Organization affiliation role definitions.';
    }
    public function dependencies(): array
    {
        return [new SeedId('20260901040200_seed_organization_catalog_and_authorization')];
    }

    public function steps(): array
    {
        $definitions = [
            ['01a0474d-b404-7100-8000-000000000001','MEMBER','MEMBERSHIP','NORMAL',null],
            ['01a0474d-b404-7100-8000-000000000002','STUDENT','STUDY','NORMAL',null],
            ['01a0474d-b404-7100-8000-000000000003','MEMORIZER','QURAN_PARTICIPATION','NORMAL','MEMORIZER'],
            ['01a0474d-b404-7100-8000-000000000004','RECITER','QURAN_PARTICIPATION','NORMAL','RECITER'],
            ['01a0474d-b404-7100-8000-000000000005','TEACHER','TEACHING','NORMAL',null],
            ['01a0474d-b404-7100-8000-000000000006','QURAN_TEACHER','TEACHING','NORMAL',null],
            ['01a0474d-b404-7100-8000-000000000007','IMAM','RELIGIOUS_SERVICE','NORMAL',null],
            ['01a0474d-b404-7100-8000-000000000008','MUADHDHIN','RELIGIOUS_SERVICE','NORMAL',null],
            ['01a0474d-b404-7100-8000-000000000009','STAFF','STAFF','NORMAL',null],
            ['01a0474d-b404-7100-8000-000000000010','VOLUNTEER','VOLUNTEERING','NORMAL',null],
            ['01a0474d-b404-7100-8000-000000000011','LEADER','LEADERSHIP','LEADERSHIP',null],
            ['01a0474d-b404-7100-8000-000000000012','REPRESENTATIVE','REPRESENTATION','LEADERSHIP',null],
        ];
        $steps = [];
        foreach ($definitions as $index => [$publicId, $code, $category, $sensitivity, $requiredPersonRole]) {
            $steps[] = new SqlSeedStep(new SeedStepId(sprintf('%03d_role_%s', $index + 1, strtolower($code))), 'Insert a governed Organization affiliation role definition.', 'INSERT INTO organization_affiliation_role_definitions (public_id,code,category,sensitivity_level,required_person_role_type,status,sort_order,version,created_at,updated_at,retired_at) VALUES (UUID_TO_BIN(:public_id),:code,:category,:sensitivity,:required_person_role_type,\'ACTIVE\',:sort_order,1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL)', [':public_id' => $publicId,':code' => $code,':category' => $category,':sensitivity' => $sensitivity,':required_person_role_type' => $requiredPersonRole,':sort_order' => $index]);
        }

        return $steps;
    }
}
