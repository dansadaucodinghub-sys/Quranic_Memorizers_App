<?php

declare(strict_types=1);

namespace Qmdb\Modules\SearchAnalytics\Infrastructure\Seed;

use Qmdb\Modules\SecurityAuthorization\Domain\SearchAnalyticsP11AuthorizationCatalog;
use Qmdb\Shared\Schema\Seed\Seed;
use Qmdb\Shared\Schema\Seed\SeedId;
use Qmdb\Shared\Schema\Seed\SeedStepId;
use Qmdb\Shared\Schema\Seed\SqlSeedStep;

final readonly class SeedP11SearchAnalyticsReportingCatalog implements Seed
{
    public function id(): SeedId
    {
        return new SeedId('20260925110000_seed_p11_search_analytics_reporting_catalog');
    }

    public function description(): string
    {
        return 'Seed P11 authority, privacy, metric, dashboard, and report catalogs.';
    }

    public function dependencies(): array
    {
        return [new SeedId('20260922102000_seed_p10_community_authorization')];
    }

    public function steps(): array
    {
        $steps = [];
        $position = 1;
        foreach (SearchAnalyticsP11AuthorizationCatalog::PERMISSIONS as [$id, $code, $scope, $assurance]) {
            $steps[] = new SqlSeedStep(
                new SeedStepId(sprintf('%03d_permission', $position++)),
                'Insert one P11 permission.',
                "INSERT INTO authorization_permissions (public_id,code,scope_type,required_assurance_level,status,owning_module,version,created_at,updated_at,retired_at) VALUES (UUID_TO_BIN(:id),:code,:scope,:assurance,'ACTIVE','search.analytics_reporting',1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL)",
                [':id' => $id, ':code' => $code, ':scope' => $scope, ':assurance' => $assurance],
            );
        }
        foreach (SearchAnalyticsP11AuthorizationCatalog::ROLES as [$id, $code, $scope]) {
            $steps[] = new SqlSeedStep(
                new SeedStepId(sprintf('%03d_role', $position++)),
                'Insert one P11 role.',
                "INSERT INTO authorization_roles (public_id,code,scope_type,status,is_system,version,created_at,updated_at,retired_at) VALUES (UUID_TO_BIN(:id),:code,:scope,'ACTIVE',1,1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL)",
                [':id' => $id, ':code' => $code, ':scope' => $scope],
            );
        }
        foreach (SearchAnalyticsP11AuthorizationCatalog::mappings() as $role => $permissions) {
            foreach ($permissions as $permission) {
                $steps[] = new SqlSeedStep(
                    new SeedStepId(sprintf('%03d_mapping', $position++)),
                    'Map one P11 role permission.',
                    'INSERT INTO authorization_role_permissions (role_id,role_scope_type,permission_id,permission_scope_type,created_at) SELECT r.id,r.scope_type,p.id,p.scope_type,UTC_TIMESTAMP(6) FROM authorization_roles r INNER JOIN authorization_permissions p ON p.code=:permission WHERE r.code=:role',
                    [':role' => $role, ':permission' => $permission],
                );
            }
        }

        $steps[] = $this->sql($position++, 'privacy_public', 'Insert public small-group disclosure policy.', <<<'SQL'
INSERT INTO analytics_privacy_policies (public_id,code,policy_type,minimum_cell_size,rounding_bucket,differencing_window_seconds,status_code,version,checksum,activated_at,retired_at,created_at,updated_at) VALUES (UUID_TO_BIN('01998f64-7000-7000-8000-000000000001'),'PUBLIC_SMALL_GROUP','MINIMUM_CELL_SIZE',5,5,86400,'ACTIVE',1,UNHEX(SHA2('PUBLIC_SMALL_GROUP:v1',256)),UTC_TIMESTAMP(6),NULL,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))
SQL);
        $steps[] = $this->sql($position++, 'privacy_workspace', 'Insert workspace disclosure policy.', <<<'SQL'
INSERT INTO analytics_privacy_policies (public_id,code,policy_type,minimum_cell_size,rounding_bucket,differencing_window_seconds,status_code,version,checksum,activated_at,retired_at,created_at,updated_at) VALUES (UUID_TO_BIN('01998f64-7000-7000-8000-000000000002'),'WORKSPACE_MINIMIZED','MINIMUM_CELL_SIZE',3,1,3600,'ACTIVE',1,UNHEX(SHA2('WORKSPACE_MINIMIZED:v1',256)),UTC_TIMESTAMP(6),NULL,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))
SQL);
        $steps[] = $this->sql($position++, 'privacy_platform', 'Insert platform-private disclosure policy.', <<<'SQL'
INSERT INTO analytics_privacy_policies (public_id,code,policy_type,minimum_cell_size,rounding_bucket,differencing_window_seconds,status_code,version,checksum,activated_at,retired_at,created_at,updated_at) VALUES (UUID_TO_BIN('01998f64-7000-7000-8000-000000000003'),'PLATFORM_PRIVATE','PLATFORM_PRIVATE_ONLY',1,1,0,'ACTIVE',1,UNHEX(SHA2('PLATFORM_PRIVATE:v1',256)),UTC_TIMESTAMP(6),NULL,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))
SQL);
        foreach ($this->metrics() as [$id, $code, $scope, $formula, $privacy]) {
            $steps[] = new SqlSeedStep(
                new SeedStepId(sprintf('%03d_metric', $position++)),
                'Insert one governed P11 metric.',
                "INSERT INTO analytics_metric_definitions (public_id,code,label_key,scope_code,formula_code,dimensions_json,privacy_policy_id,status_code,version,checksum,activated_at,retired_at,created_at,updated_at) SELECT UUID_TO_BIN(:id),:code,:label_key,:scope,:formula,JSON_ARRAY(),p.id,'ACTIVE',1,UNHEX(SHA2(:checksum_input,256)),UTC_TIMESTAMP(6),NULL,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6) FROM analytics_privacy_policies p WHERE p.code=:privacy AND p.status_code='ACTIVE'",
                [':id' => $id, ':code' => $code, ':label_key' => 'analytics.metric.' . strtolower($code), ':scope' => $scope, ':formula' => $formula, ':checksum_input' => $code . ':v1', ':privacy' => $privacy],
            );
        }
        $steps[] = $this->sql($position++, 'dashboard_workspace', 'Insert workspace operations dashboard.', <<<'SQL'
INSERT INTO analytics_dashboard_definitions (public_id,code,label_key,scope_code,status_code,version,checksum,activated_at,retired_at,created_at,updated_at) VALUES (UUID_TO_BIN('01998f66-7000-7000-8000-000000000001'),'WORKSPACE_OPERATIONS','analytics.dashboard.workspace_operations','WORKSPACE','ACTIVE',1,UNHEX(SHA2('WORKSPACE_OPERATIONS:v1',256)),UTC_TIMESTAMP(6),NULL,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))
SQL);
        $steps[] = $this->sql($position++, 'dashboard_public', 'Insert public national statistics dashboard.', <<<'SQL'
INSERT INTO analytics_dashboard_definitions (public_id,code,label_key,scope_code,status_code,version,checksum,activated_at,retired_at,created_at,updated_at) VALUES (UUID_TO_BIN('01998f66-7000-7000-8000-000000000002'),'PUBLIC_NATIONAL_STATISTICS','analytics.dashboard.public_national_statistics','PUBLIC','ACTIVE',1,UNHEX(SHA2('PUBLIC_NATIONAL_STATISTICS:v1',256)),UTC_TIMESTAMP(6),NULL,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))
SQL);
        $steps[] = $this->sql($position++, 'dashboard_widgets', 'Compose dashboards from governed metrics.', <<<'SQL'
INSERT INTO analytics_dashboard_widgets (dashboard_definition_id,metric_definition_id,widget_code,presentation_code,position,created_at)
SELECT d.id,m.id,LOWER(m.code),'NUMBER',ROW_NUMBER() OVER (PARTITION BY d.id ORDER BY m.code),UTC_TIMESTAMP(6)
FROM analytics_dashboard_definitions d INNER JOIN analytics_metric_definitions m ON m.status_code='ACTIVE' AND m.scope_code=d.scope_code
WHERE d.status_code='ACTIVE'
SQL);
        foreach ($this->reports() as [$id, $code, $scope, $query, $privacy, $sensitive, $approval]) {
            $steps[] = new SqlSeedStep(
                new SeedStepId(sprintf('%03d_report', $position++)),
                'Insert one governed P11 report definition.',
                "INSERT INTO report_definitions (public_id,code,label_key,scope_code,query_code,allowed_formats_json,privacy_policy_id,row_limit,byte_limit,sensitive_flag,approval_required,status_code,version,checksum,activated_at,retired_at,created_at,updated_at) SELECT UUID_TO_BIN(:id),:code,:label_key,:scope,:query,JSON_ARRAY('CSV','JSON'),p.id,10000,10485760,:sensitive,:approval,'ACTIVE',1,UNHEX(SHA2(:checksum_input,256)),UTC_TIMESTAMP(6),NULL,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6) FROM analytics_privacy_policies p WHERE p.code=:privacy AND p.status_code='ACTIVE'",
                [':id' => $id, ':code' => $code, ':label_key' => 'report.definition.' . strtolower($code), ':scope' => $scope, ':query' => $query, ':privacy' => $privacy, ':sensitive' => $sensitive, ':approval' => $approval, ':checksum_input' => $code . ':v1'],
            );
        }
        return $steps;
    }

    private function sql(int $position, string $suffix, string $description, string $sql): SqlSeedStep
    {
        return new SqlSeedStep(new SeedStepId(sprintf('%03d_%s', $position, $suffix)), $description, $sql);
    }

    /** @return list<array{string,string,string,string,string}> */
    private function metrics(): array
    {
        return [
            ['01998f65-7000-7000-8000-000000000001', 'WORKSPACE_SEARCHABLE_RECORDS', 'WORKSPACE', 'COUNT_SEARCH_DOCUMENTS', 'WORKSPACE_MINIMIZED'],
            ['01998f65-7000-7000-8000-000000000002', 'PUBLIC_PUBLISHED_RESULTS', 'PUBLIC', 'COUNT_PUBLISHED_RESULTS', 'PUBLIC_SMALL_GROUP'],
            ['01998f65-7000-7000-8000-000000000003', 'PUBLIC_ISSUED_CERTIFICATES', 'PUBLIC', 'COUNT_ISSUED_CERTIFICATES', 'PUBLIC_SMALL_GROUP'],
            ['01998f65-7000-7000-8000-000000000004', 'PUBLIC_RECITATION_CLIPS', 'PUBLIC', 'COUNT_PUBLISHED_CLIPS', 'PUBLIC_SMALL_GROUP'],
            ['01998f65-7000-7000-8000-000000000005', 'PLATFORM_SEARCHABLE_RECORDS', 'PLATFORM', 'COUNT_SEARCH_DOCUMENTS', 'PLATFORM_PRIVATE'],
        ];
    }

    /** @return list<array{string,string,string,string,string,int,int}> */
    private function reports(): array
    {
        return [
            ['01998f67-7000-7000-8000-000000000001', 'WORKSPACE_SEARCH_INVENTORY', 'WORKSPACE', 'WORKSPACE_SEARCH_INVENTORY', 'WORKSPACE_MINIMIZED', 0, 0],
            ['01998f67-7000-7000-8000-000000000002', 'WORKSPACE_RESULTS_SUMMARY', 'WORKSPACE', 'WORKSPACE_RESULTS_SUMMARY', 'WORKSPACE_MINIMIZED', 1, 1],
            ['01998f67-7000-7000-8000-000000000003', 'NATIONAL_AGGREGATE_SUMMARY', 'PLATFORM', 'NATIONAL_AGGREGATE_SUMMARY', 'PUBLIC_SMALL_GROUP', 1, 1],
        ];
    }
}
