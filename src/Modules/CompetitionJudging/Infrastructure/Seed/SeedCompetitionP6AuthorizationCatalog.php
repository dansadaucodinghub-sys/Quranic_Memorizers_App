<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionJudging\Infrastructure\Seed;

use Qmdb\Shared\Schema\Seed\Seed;
use Qmdb\Shared\Schema\Seed\SeedId;
use Qmdb\Shared\Schema\Seed\SeedStepId;
use Qmdb\Shared\Schema\Seed\SqlSeedStep;

final readonly class SeedCompetitionP6AuthorizationCatalog implements Seed
{
    public function id(): SeedId { return new SeedId('20260911141000_seed_competition_p6_authorization_catalog'); }
    public function description(): string { return 'Seed exact P6 judging, scoring, result, and appeal workspace permissions.'; }
    public function dependencies(): array { return [new SeedId('20260911131100_seed_competition_authorization_role_mappings')]; }
    public function steps(): array
    {
        $rows = [
            ['01a06000-0000-7001-8000-000000000001','workspace.competitions.manage_judges','MULTI_FACTOR'],
            ['01a06000-0000-7001-8000-000000000002','workspace.competitions.manage_rounds','MULTI_FACTOR'],
            ['01a06000-0000-7001-8000-000000000003','workspace.competitions.manage_rubrics','MULTI_FACTOR'],
            ['01a06000-0000-7001-8000-000000000004','workspace.competitions.score','MULTI_FACTOR'],
            ['01a06000-0000-7001-8000-000000000005','workspace.competitions.calculate_results','MULTI_FACTOR'],
            ['01a06000-0000-7001-8000-000000000006','workspace.competitions.verify_results','PHISHING_RESISTANT'],
            ['01a06000-0000-7001-8000-000000000007','workspace.competitions.publish_results','PHISHING_RESISTANT'],
            ['01a06000-0000-7001-8000-000000000008','workspace.competitions.review_appeals','PHISHING_RESISTANT'],
        ];
        $steps = [];
        foreach ($rows as $index => [$id, $code, $assurance]) {
            $steps[] = new SqlSeedStep(new SeedStepId(sprintf('%03d_permission', $index + 1)), 'Insert one exact P6 workspace permission.', "INSERT INTO authorization_permissions (public_id,code,scope_type,required_assurance_level,status,owning_module,version,created_at,updated_at,retired_at) VALUES (UUID_TO_BIN(:id),:code,'WORKSPACE',:assurance,'ACTIVE','competition.results',1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL)", [':id' => $id, ':code' => $code, ':assurance' => $assurance]);
        }
        return $steps;
    }
}
