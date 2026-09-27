<?php

declare(strict_types=1);

namespace Qmdb\Modules\ProductionHardening\Infrastructure\Seed;

use Qmdb\Modules\SecurityAuthorization\Domain\ProductionHardeningP12AuthorizationCatalog;
use Qmdb\Shared\Schema\Seed\Seed;
use Qmdb\Shared\Schema\Seed\SeedId;
use Qmdb\Shared\Schema\Seed\SeedStepId;
use Qmdb\Shared\Schema\Seed\SqlSeedStep;

final readonly class SeedP12ProductionHardeningCatalog implements Seed
{
    public function id(): SeedId
    {
        return new SeedId('20260927110000_seed_p12_production_hardening_catalog');
    }

    public function description(): string
    {
        return 'Seed P12 authorization, notification, retention, service, and SLI catalogs.';
    }

    public function dependencies(): array
    {
        return [new SeedId('20260925110000_seed_p11_search_analytics_reporting_catalog')];
    }

    public function steps(): array
    {
        $steps = [];
        $position = 1;
        foreach (ProductionHardeningP12AuthorizationCatalog::PERMISSIONS as [$id, $code, $scope, $assurance]) {
            $steps[] = new SqlSeedStep(
                new SeedStepId(sprintf('%03d_permission', $position++)),
                'Insert one P12 permission.',
                "INSERT INTO authorization_permissions (public_id,code,scope_type,required_assurance_level,status,owning_module,version,created_at,updated_at,retired_at) VALUES (UUID_TO_BIN(:id),:code,:scope,:assurance,'ACTIVE','production.hardening',1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL)",
                [':id' => $id, ':code' => $code, ':scope' => $scope, ':assurance' => $assurance],
            );
        }
        foreach (ProductionHardeningP12AuthorizationCatalog::ROLES as [$id, $code, $scope]) {
            $steps[] = new SqlSeedStep(
                new SeedStepId(sprintf('%03d_role', $position++)),
                'Insert one P12 role.',
                "INSERT INTO authorization_roles (public_id,code,scope_type,status,is_system,version,created_at,updated_at,retired_at) VALUES (UUID_TO_BIN(:id),:code,:scope,'ACTIVE',1,1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL)",
                [':id' => $id, ':code' => $code, ':scope' => $scope],
            );
        }
        foreach (ProductionHardeningP12AuthorizationCatalog::mappings() as $role => $permissions) {
            foreach ($permissions as $permission) {
                $steps[] = new SqlSeedStep(
                    new SeedStepId(sprintf('%03d_mapping', $position++)),
                    'Map one P12 role permission.',
                    'INSERT INTO authorization_role_permissions (role_id,role_scope_type,permission_id,permission_scope_type,created_at) SELECT r.id,r.scope_type,p.id,p.scope_type,UTC_TIMESTAMP(6) FROM authorization_roles r INNER JOIN authorization_permissions p ON p.code=:permission WHERE r.code=:role',
                    [':role' => $role, ':permission' => $permission],
                );
            }
        }

        foreach ($this->processingPurposes() as [$code, $name]) {
            $steps[] = new SqlSeedStep(
                new SeedStepId(sprintf('%03d_purpose', $position++)),
                'Insert one governed processing purpose.',
                'INSERT INTO processing_purposes (code,name,effective_from,effective_until,created_at) VALUES (:code,:name,UTC_TIMESTAMP(6),NULL,UTC_TIMESTAMP(6))',
                [':code' => $code, ':name' => $name],
            );
            $steps[] = new SqlSeedStep(
                new SeedStepId(sprintf('%03d_notice', $position++)),
                'Insert the initial immutable privacy-notice version.',
                "INSERT INTO privacy_notice_versions (processing_purpose_id,version_number,content_checksum,status_code,effective_from,created_at) SELECT id,1,UNHEX(SHA2(:checksum_material,256)),'ACTIVE',UTC_TIMESTAMP(6),UTC_TIMESTAMP(6) FROM processing_purposes WHERE code=:code",
                [':code' => $code, ':checksum_material' => $code . ':notice:v1'],
            );
        }

        foreach ($this->templates() as [$id, $code, $locale, $channel, $subject, $body, $classification, $mandatory]) {
            $steps[] = new SqlSeedStep(
                new SeedStepId(sprintf('%03d_template', $position++)),
                'Insert one localized notification template.',
                "INSERT INTO notification_templates (public_id,template_code,locale,channel_code,subject_template,body_template,classification_code,mandatory_flag,version,checksum,status_code,created_at,retired_at) VALUES (UUID_TO_BIN(:id),:code,:locale,:channel,:subject,:body,:classification,:mandatory,1,UNHEX(SHA2(:checksum_material,256)),'ACTIVE',UTC_TIMESTAMP(6),NULL)",
                [':id' => $id, ':code' => $code, ':locale' => $locale, ':channel' => $channel, ':subject' => $subject, ':body' => $body, ':classification' => $classification, ':mandatory' => $mandatory, ':checksum_material' => $code . ':' . $locale . ':' . $channel . ':v1'],
            );
        }

        foreach ($this->retentionPolicies() as [$id, $code, $subject, $purpose, $days, $disposition]) {
            $steps[] = new SqlSeedStep(
                new SeedStepId(sprintf('%03d_retention', $position++)),
                'Insert one governed retention policy.',
                "INSERT INTO retention_policy_records (public_id,policy_code,subject_kind,purpose_code,retention_days,disposition_code,legal_basis_code,policy_json,version,checksum,status_code,effective_at,retired_at,created_at) VALUES (UUID_TO_BIN(:id),:code,:subject,:purpose,:days,:disposition,'APPROVED_BASELINE',JSON_OBJECT('holdRequired',true,'dryRunRequired',true),1,UNHEX(SHA2(:checksum_material,256)),'ACTIVE',UTC_TIMESTAMP(6),NULL,UTC_TIMESTAMP(6))",
                [':id' => $id, ':code' => $code, ':subject' => $subject, ':purpose' => $purpose, ':days' => $days, ':disposition' => $disposition, ':checksum_material' => $code . ':v1'],
            );
        }

        foreach ($this->services() as [$id, $code, $tier, $order, $owner, $dependencies, $degradation, $probe]) {
            $steps[] = new SqlSeedStep(
                new SeedStepId(sprintf('%03d_service', $position++)),
                'Insert one critical service catalog entry.',
                "INSERT INTO operational_service_catalog (public_id,service_code,criticality_code,recovery_order,owner_role_code,dependency_codes_json,degradation_mode,health_probe_code,status_code,version,created_at,updated_at) VALUES (UUID_TO_BIN(:id),:code,:tier,:recovery_order,:owner,CAST(:dependencies AS JSON),:degradation,:probe,'ACTIVE',1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))",
                [':id' => $id, ':code' => $code, ':tier' => $tier, ':recovery_order' => $order, ':owner' => $owner, ':dependencies' => $dependencies, ':degradation' => $degradation, ':probe' => $probe],
            );
        }

        foreach ($this->slis() as [$id, $service, $code, $metric, $comparator, $target, $window, $unit]) {
            $steps[] = new SqlSeedStep(
                new SeedStepId(sprintf('%03d_sli', $position++)),
                'Insert one parameterized service-level indicator.',
                "INSERT INTO operational_sli_definitions (public_id,service_id,sli_code,metric_code,comparator_code,target_value,window_seconds,unit_code,status_code,version,created_at) SELECT UUID_TO_BIN(:id),id,:code,:metric,:comparator,:target,:window,:unit,'ACTIVE',1,UTC_TIMESTAMP(6) FROM operational_service_catalog WHERE service_code=:service",
                [':id' => $id, ':service' => $service, ':code' => $code, ':metric' => $metric, ':comparator' => $comparator, ':target' => $target, ':window' => $window, ':unit' => $unit],
            );
        }

        return $steps;
    }

    /** @return list<array{string,string}> */
    private function processingPurposes(): array
    {
        return [
            ['USER_COMMUNICATION', 'Account and service communications'],
            ['INTEGRATION_EVIDENCE', 'Integration delivery and security evidence'],
            ['LEGAL_ACCOUNTABILITY', 'Privacy rights and legal accountability'],
            ['SECURITY_ACCOUNTABILITY', 'Security, audit, and incident accountability'],
        ];
    }

    /** @return list<array{string,string,string,string,string,string,string,int}> */
    private function templates(): array
    {
        return [
            ['01999a14-7000-7000-8000-000000000001', 'PRIVACY_REQUEST_STATUS', 'en', 'IN_APP', 'Privacy request update', 'Your privacy request status changed. Open the protected request for details.', 'RESTRICTED', 1],
            ['01999a14-7000-7000-8000-000000000002', 'PRIVACY_REQUEST_STATUS', 'ar', 'IN_APP', 'تحديث طلب الخصوصية', 'تغيرت حالة طلب الخصوصية. افتح الطلب المحمي للاطلاع على التفاصيل.', 'RESTRICTED', 1],
            ['01999a14-7000-7000-8000-000000000003', 'SECURITY_INCIDENT_NOTICE', 'en', 'IN_APP', 'Security notice', 'A security action requires your attention. Open the protected security page.', 'RESTRICTED', 1],
            ['01999a14-7000-7000-8000-000000000004', 'SECURITY_INCIDENT_NOTICE', 'ar', 'IN_APP', 'إشعار أمني', 'يتطلب إجراء أمني انتباهك. افتح صفحة الأمان المحمية.', 'RESTRICTED', 1],
            ['01999a14-7000-7000-8000-000000000005', 'INTEGRATION_SUSPENDED', 'en', 'IN_APP', 'Integration suspended', 'An integration was suspended after governed delivery failures.', 'INTERNAL', 1],
            ['01999a14-7000-7000-8000-000000000006', 'INTEGRATION_SUSPENDED', 'ar', 'IN_APP', 'تم تعليق التكامل', 'تم تعليق تكامل بعد إخفاقات تسليم خاضعة للحوكمة.', 'INTERNAL', 1],
        ];
    }

    /** @return list<array{string,string,string,string,int,string}> */
    private function retentionPolicies(): array
    {
        return [
            ['01999a15-7000-7000-8000-000000000001', 'NOTIFICATION_OPERATIONAL', 'NOTIFICATION', 'USER_COMMUNICATION', 730, 'DELETE'],
            ['01999a15-7000-7000-8000-000000000002', 'INTEGRATION_DELIVERY', 'WEBHOOK_DELIVERY', 'INTEGRATION_EVIDENCE', 1095, 'ANONYMIZE'],
            ['01999a15-7000-7000-8000-000000000003', 'PRIVACY_CASE', 'PRIVACY_REQUEST', 'LEGAL_ACCOUNTABILITY', 2555, 'REVIEW'],
            ['01999a15-7000-7000-8000-000000000004', 'AUDIT_EVIDENCE', 'AUDIT_EVENT', 'SECURITY_ACCOUNTABILITY', 3650, 'ARCHIVE'],
        ];
    }

    /** @return list<array{string,string,string,int,string,string,string,string}> */
    private function services(): array
    {
        return [
            ['01999a16-7000-7000-8000-000000000001', 'mysql.authority', 'TIER_0', 1, 'platform.recovery_operator', '[]', 'FAIL_CLOSED', 'database'],
            ['01999a16-7000-7000-8000-000000000002', 'identity.authorization', 'TIER_0', 2, 'platform.security_administrator', '["mysql.authority"]', 'DENY_PROTECTED', 'identity'],
            ['01999a16-7000-7000-8000-000000000003', 'http.application', 'TIER_1', 3, 'platform.security_incident_commander', '["mysql.authority","identity.authorization"]', 'READ_ONLY_PUBLIC', 'http'],
            ['01999a16-7000-7000-8000-000000000004', 'background.workers', 'TIER_1', 4, 'platform.recovery_operator', '["mysql.authority"]', 'QUEUE_AND_RECONCILE', 'scheduler'],
            ['01999a16-7000-7000-8000-000000000005', 'integrations.webhooks', 'TIER_2', 5, 'platform.integration_operator', '["mysql.authority","background.workers"]', 'RETRY_NO_ROLLBACK', 'webhooks'],
            ['01999a16-7000-7000-8000-000000000006', 'notifications.delivery', 'TIER_2', 6, 'platform.integration_operator', '["mysql.authority","background.workers"]', 'DEFER_NO_ROLLBACK', 'notifications'],
        ];
    }

    /** @return list<array{string,string,string,string,string,float,int,string}> */
    private function slis(): array
    {
        return [
            ['01999a17-7000-7000-8000-000000000001', 'http.application', 'HTTP_P95_LATENCY', 'http.server.duration.p95', 'LTE', 750.0, 300, 'MILLISECONDS'],
            ['01999a17-7000-7000-8000-000000000002', 'http.application', 'HTTP_ERROR_RATE', 'http.server.error_rate', 'LTE', 1.0, 300, 'PERCENT'],
            ['01999a17-7000-7000-8000-000000000003', 'background.workers', 'WORKER_OLDEST_AGE', 'worker.oldest_due_age', 'LTE', 300.0, 300, 'SECONDS'],
            ['01999a17-7000-7000-8000-000000000004', 'integrations.webhooks', 'WEBHOOK_DEAD_LETTER_RATE', 'webhook.dead_letter_rate', 'LTE', 2.0, 3600, 'PERCENT'],
            ['01999a17-7000-7000-8000-000000000005', 'notifications.delivery', 'NOTIFICATION_DEAD_LETTER_RATE', 'notification.dead_letter_rate', 'LTE', 2.0, 3600, 'PERCENT'],
        ];
    }
}
