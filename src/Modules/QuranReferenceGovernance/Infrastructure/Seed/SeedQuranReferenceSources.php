<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Infrastructure\Seed;

use Qmdb\Shared\Schema\Seed\Seed;
use Qmdb\Shared\Schema\Seed\SeedId;
use Qmdb\Shared\Schema\Seed\SeedStepId;
use Qmdb\Shared\Schema\Seed\SqlSeedStep;

final readonly class SeedQuranReferenceSources implements Seed
{
    public function id(): SeedId
    {
        return new SeedId('20260910060100_seed_quran_reference_sources');
    }
    public function description(): string
    {
        return 'Seed the three approved Tanzil source definitions without text or artifacts.';
    }
    public function dependencies(): array
    {
        return [new SeedId('20260902050100_seed_people_identity_resolution_authorization')];
    }
    public function steps(): array
    {
        $records = [
            ['01a0474d-c401-7001-8000-000000000001','TANZIL_UTHMANI_1_1','Tanzil Qur’an Text — Uthmani','1.1','CANONICAL_TEXT','UTF8_TEXT_WITH_AYAH_IDENTIFIERS','TANZIL_CC_BY_3_VERBATIM'],
            ['01a0474d-c401-7001-8000-000000000002','TANZIL_QURAN_METADATA_1_0','Tanzil Qur’an Metadata','1.0','STRUCTURAL_METADATA','XML','TANZIL_RESOURCE_ATTRIBUTION'],
            ['01a0474d-c401-7001-8000-000000000003','TANZIL_SIMPLE_CLEAN_1_1','Tanzil Qur’an Text — Simple Clean','1.1','SEARCH_TEXT','UTF8_TEXT_WITH_AYAH_IDENTIFIERS','TANZIL_CC_BY_3_VERBATIM'],
        ];
        $steps = [];
        foreach ($records as $index => [$publicId,$code,$title,$version,$role,$format,$terms]) {
            $steps[] = new SqlSeedStep(new SeedStepId(sprintf('%03d_source', $index + 1)), 'Insert one approved Tanzil source definition.', 'INSERT INTO quran_reference_sources (public_id,source_code,authority_name,source_title,source_version,content_role,source_format,source_reference,usage_terms_code,attribution_required,verbatim_only,runtime_download_allowed,status,version,created_at,updated_at,retired_at) VALUES (UUID_TO_BIN(:public_id),:source_code,\'Tanzil Project\',:source_title,:source_version,:content_role,:source_format,:source_reference,:usage_terms_code,1,1,0,\'APPROVED\',1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL)', [':public_id' => $publicId,':source_code' => $code,':source_title' => $title,':source_version' => $version,':content_role' => $role,':source_format' => $format,':source_reference' => 'https://tanzil.net/download/',':usage_terms_code' => $terms]);
        }
        return $steps;
    }
}
