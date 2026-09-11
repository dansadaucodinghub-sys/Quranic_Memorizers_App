<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Infrastructure\Seed;

use Qmdb\Shared\Schema\Seed\Seed;
use Qmdb\Shared\Schema\Seed\SeedId;
use Qmdb\Shared\Schema\Seed\SeedStepId;
use Qmdb\Shared\Schema\Seed\SqlSeedStep;

/** Adds only B03 permissions; the applied B01 governance seed remains immutable. */
final readonly class SeedQuranSearchCorpusAuthorization implements Seed
{
    public function id(): SeedId
    {
        return new SeedId('20260911110500_seed_quran_search_corpus_authorization');
    }
    public function description(): string
    {
        return 'Seed exact Platform Qur’an search-corpus integrity permissions and mappings.';
    }
    public function dependencies(): array
    {
        return [new SeedId('20260910060200_seed_quran_governance_authorization')];
    }

    public function steps(): array
    {
        $permissions = [
            ['01a0474d-c402-7001-8000-000000000007', 'platform.quran_search_corpus.view', 'MULTI_FACTOR'],
            ['01a0474d-c402-7001-8000-000000000008', 'platform.quran_search_corpus.validate', 'PHISHING_RESISTANT'],
            ['01a0474d-c402-7001-8000-000000000009', 'platform.quran_public_reference.verify', 'MULTI_FACTOR'],
        ];
        $steps = [];
        foreach ($permissions as $position => [$id, $code, $assurance]) {
            $steps[] = new SqlSeedStep(new SeedStepId(sprintf('%03d_permission', $position + 1)), 'Insert one exact Qur’an search-corpus permission.', 'INSERT INTO authorization_permissions (public_id,code,scope_type,required_assurance_level,status,owning_module,version,created_at,updated_at,retired_at) VALUES (UUID_TO_BIN(:id),:code,\'PLATFORM\',:assurance,\'ACTIVE\',\'quran.reference_governance\',1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL)', [':id' => $id, ':code' => $code, ':assurance' => $assurance]);
        }
        $position = 4;
        foreach (['01a0474d-a8f0-7e7b-b399-7a15c5dcbcd5', '01a0474d-c402-7001-8000-000000000006'] as $roleId) {
            foreach ($permissions as [$permissionId]) {
                $steps[] = $this->mapping($position++, $roleId, $permissionId, 'Map a governing Platform role to an exact search-corpus permission.');
            }
        }
        foreach ([$permissions[0][0], $permissions[2][0]] as $permissionId) {
            $steps[] = $this->mapping($position++, '01a0474d-a8f0-7178-97c8-4829dcc9d733', $permissionId, 'Map the authorization auditor to a read-only Qur’an integrity permission.');
        }

        return $steps;
    }

    private function mapping(int $position, string $roleId, string $permissionId, string $description): SqlSeedStep
    {
        return new SqlSeedStep(new SeedStepId(sprintf('%03d_mapping', $position)), $description, 'INSERT INTO authorization_role_permissions (role_id,role_scope_type,permission_id,permission_scope_type,created_at) SELECT r.id,r.scope_type,p.id,p.scope_type,UTC_TIMESTAMP(6) FROM authorization_roles r INNER JOIN authorization_permissions p ON p.public_id=UUID_TO_BIN(:permission_id) WHERE r.public_id=UUID_TO_BIN(:role_id)', [':role_id' => $roleId, ':permission_id' => $permissionId]);
    }
}
