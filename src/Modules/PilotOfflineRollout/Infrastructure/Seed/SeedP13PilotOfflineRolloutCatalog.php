<?php

declare(strict_types=1);

namespace Qmdb\Modules\PilotOfflineRollout\Infrastructure\Seed;

use Qmdb\Modules\SecurityAuthorization\Domain\PilotOfflineRolloutP13AuthorizationCatalog;
use Qmdb\Shared\Schema\Seed\Seed;
use Qmdb\Shared\Schema\Seed\SeedId;
use Qmdb\Shared\Schema\Seed\SeedStepId;
use Qmdb\Shared\Schema\Seed\SqlSeedStep;

final readonly class SeedP13PilotOfflineRolloutCatalog implements Seed
{
    public function id(): SeedId
    {
        return new SeedId('20260928110000_seed_p13_pilot_offline_rollout_catalog');
    }

    public function description(): string
    {
        return 'Seed P13 authorization, offline policy, rollout policy, and bilingual notification catalogs.';
    }

    public function dependencies(): array
    {
        return [new SeedId('20260927110000_seed_p12_production_hardening_catalog')];
    }

    public function steps(): array
    {
        $steps = [];
        $position = 1;
        foreach (PilotOfflineRolloutP13AuthorizationCatalog::PERMISSIONS as [$id, $code, $scope, $assurance]) {
            $steps[] = new SqlSeedStep(
                new SeedStepId(sprintf('%03d_permission', $position++)),
                'Insert one P13 permission.',
                "INSERT INTO authorization_permissions (public_id,code,scope_type,required_assurance_level,status,owning_module,version,created_at,updated_at,retired_at) VALUES (UUID_TO_BIN(:id),:code,:scope,:assurance,'ACTIVE','pilot.offline_rollout',1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL)",
                [':id' => $id, ':code' => $code, ':scope' => $scope, ':assurance' => $assurance],
            );
        }
        foreach (PilotOfflineRolloutP13AuthorizationCatalog::ROLES as [$id, $code, $scope]) {
            $steps[] = new SqlSeedStep(
                new SeedStepId(sprintf('%03d_role', $position++)),
                'Insert one P13 role.',
                "INSERT INTO authorization_roles (public_id,code,scope_type,status,is_system,version,created_at,updated_at,retired_at) VALUES (UUID_TO_BIN(:id),:code,:scope,'ACTIVE',1,1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL)",
                [':id' => $id, ':code' => $code, ':scope' => $scope],
            );
        }
        foreach (PilotOfflineRolloutP13AuthorizationCatalog::mappings() as $role => $permissions) {
            foreach ($permissions as $permission) {
                $steps[] = new SqlSeedStep(
                    new SeedStepId(sprintf('%03d_mapping', $position++)),
                    'Map one P13 role permission.',
                    'INSERT INTO authorization_role_permissions (role_id,role_scope_type,permission_id,permission_scope_type,created_at) SELECT r.id,r.scope_type,p.id,p.scope_type,UTC_TIMESTAMP(6) FROM authorization_roles r INNER JOIN authorization_permissions p ON p.code=:permission WHERE r.code=:role',
                    [':role' => $role, ':permission' => $permission],
                );
            }
        }
        foreach ($this->flags() as [$id, $code, $module, $scope, $enabled, $configuration]) {
            $steps[] = new SqlSeedStep(new SeedStepId(sprintf('%03d_flag', $position++)), 'Insert one P13 feature flag.', "INSERT INTO feature_flags (public_id,flag_code,owning_module,status_code,version,created_at,updated_at) VALUES (UUID_TO_BIN(:id),:code,:module,'ACTIVE',1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))", [':id' => $id, ':code' => $code, ':module' => $module]);
            $steps[] = new SqlSeedStep(new SeedStepId(sprintf('%03d_flag_version', $position++)), 'Insert the initial immutable P13 feature-flag version.', "INSERT INTO feature_flag_versions (public_id,feature_flag_id,version_number,scope_code,enabled_flag,configuration_json,checksum,effective_at,created_at) SELECT UUID_TO_BIN(:version_id),id,1,:scope,:enabled,CAST(:configuration AS JSON),UNHEX(SHA2(:checksum_material,256)),UTC_TIMESTAMP(6),UTC_TIMESTAMP(6) FROM feature_flags WHERE flag_code=:code", [':version_id' => str_replace('0001', '1001', $id), ':scope' => $scope, ':enabled' => $enabled, ':configuration' => $configuration, ':checksum_material' => $code . ':v1:' . $configuration, ':code' => $code]);
        }
        foreach ($this->configurations() as [$id, $code, $configuration]) {
            $steps[] = new SqlSeedStep(new SeedStepId(sprintf('%03d_configuration', $position++)), 'Insert one immutable P13 configuration.', "INSERT INTO configuration_versions (public_id,configuration_code,version_number,configuration_json,checksum,status_code,effective_at,created_at) VALUES (UUID_TO_BIN(:id),:code,1,CAST(:configuration AS JSON),UNHEX(SHA2(:checksum_material,256)),'ACTIVE',UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))", [':id' => $id, ':code' => $code, ':configuration' => $configuration, ':checksum_material' => $code . ':v1:' . $configuration]);
        }
        foreach ($this->templates() as [$id, $code, $locale, $subject, $body]) {
            $steps[] = new SqlSeedStep(new SeedStepId(sprintf('%03d_template', $position++)), 'Insert one P13 bilingual notification template.', "INSERT INTO notification_templates (public_id,template_code,locale,channel_code,subject_template,body_template,classification_code,mandatory_flag,version,checksum,status_code,created_at,retired_at) VALUES (UUID_TO_BIN(:id),:code,:locale,'IN_APP',:subject,:body,'INTERNAL',1,1,UNHEX(SHA2(:checksum_material,256)),'ACTIVE',UTC_TIMESTAMP(6),NULL)", [':id' => $id, ':code' => $code, ':locale' => $locale, ':subject' => $subject, ':body' => $body, ':checksum_material' => $code . ':' . $locale . ':v1']);
        }

        return $steps;
    }

    /** @return list<array{string,string,string,string,int,string}> */
    private function flags(): array
    {
        return [
            ['01999b03-7000-7000-8000-000000000001', 'p13.pilot_control', 'pilot.offline_rollout', 'PLATFORM', 0, '{"automaticActivation":false}'],
            ['01999b03-7000-7000-8000-000000000002', 'p13.offline_venue', 'pilot.offline_rollout', 'WORKSPACE', 0, '{"manualSyncOnly":true}'],
            ['01999b03-7000-7000-8000-000000000003', 'p13.national_rollout', 'pilot.offline_rollout', 'PLATFORM', 0, '{"automaticActivation":false,"requiresGoDecision":true}'],
        ];
    }

    /** @return list<array{string,string,string}> */
    private function configurations(): array
    {
        return [
            ['01999b04-7000-7000-8000-000000000001', 'P13_DEVICE_POLICY', '{"clockSkewSeconds":300,"nonceRetentionSeconds":900,"keyLifetimeDays":90}'],
            ['01999b04-7000-7000-8000-000000000002', 'P13_PACKAGE_POLICY', '{"expiryHours":24,"maximumBytes":268435456,"maximumEntities":10000,"encryption":"XCHACHA20_POLY1305","signing":"ED25519"}'],
            ['01999b04-7000-7000-8000-000000000003', 'P13_SYNC_POLICY', '{"maximumChanges":100,"maximumBytes":2097152,"itemBytes":32768,"silentLastWriteWins":false}'],
            ['01999b04-7000-7000-8000-000000000004', 'P13_ROLLOUT_POLICY', '{"maximumWaveSize":500,"automaticActivation":false,"goDecisionRequired":true}'],
        ];
    }

    /** @return list<array{string,string,string,string,string}> */
    private function templates(): array
    {
        return [
            ['01999b05-7000-7000-8000-000000000001', 'P13_PILOT_STATUS', 'en', 'Pilot status changed', 'A governed pilot status changed. Open the protected pilot workspace for details.'],
            ['01999b05-7000-7000-8000-000000000002', 'P13_PILOT_STATUS', 'ar', 'تغيرت حالة التجربة', 'تغيرت حالة تجربة خاضعة للحوكمة. افتح مساحة التجربة المحمية للتفاصيل.'],
            ['01999b05-7000-7000-8000-000000000003', 'P13_ROLLOUT_STATUS', 'en', 'Rollout status changed', 'A governed rollout wave status changed. Open the protected rollout workspace.'],
            ['01999b05-7000-7000-8000-000000000004', 'P13_ROLLOUT_STATUS', 'ar', 'تغيرت حالة النشر', 'تغيرت حالة موجة نشر خاضعة للحوكمة. افتح مساحة النشر المحمية.'],
            ['01999b05-7000-7000-8000-000000000005', 'P13_DEVICE_STATUS', 'en', 'Offline device status changed', 'An offline venue device status changed. Review it in the protected workspace.'],
            ['01999b05-7000-7000-8000-000000000006', 'P13_DEVICE_STATUS', 'ar', 'تغيرت حالة جهاز العمل دون اتصال', 'تغيرت حالة جهاز موقع يعمل دون اتصال. راجعه في مساحة العمل المحمية.'],
            ['01999b05-7000-7000-8000-000000000007', 'P13_PACKAGE_STATUS', 'en', 'Offline package status changed', 'An offline package requires attention in the protected workspace.'],
            ['01999b05-7000-7000-8000-000000000008', 'P13_PACKAGE_STATUS', 'ar', 'تغيرت حالة حزمة العمل دون اتصال', 'تتطلب حزمة عمل دون اتصال الانتباه في مساحة العمل المحمية.'],
            ['01999b05-7000-7000-8000-000000000009', 'P13_SYNC_STATUS', 'en', 'Offline synchronisation update', 'Offline synchronisation completed or requires protected review.'],
            ['01999b05-7000-7000-8000-00000000000a', 'P13_SYNC_STATUS', 'ar', 'تحديث المزامنة دون اتصال', 'اكتملت المزامنة دون اتصال أو تتطلب مراجعة محمية.'],
            ['01999b05-7000-7000-8000-00000000000b', 'P13_CONFLICT_STATUS', 'en', 'Offline conflict requires review', 'A private offline conflict requires an authorized reviewer.'],
            ['01999b05-7000-7000-8000-00000000000c', 'P13_CONFLICT_STATUS', 'ar', 'يتطلب تعارض العمل دون اتصال مراجعة', 'يتطلب تعارض خاص بالعمل دون اتصال مراجعًا مخولًا.'],
        ];
    }
}
