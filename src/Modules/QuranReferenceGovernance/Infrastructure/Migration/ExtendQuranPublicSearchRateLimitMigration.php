<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class ExtendQuranPublicSearchRateLimitMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260911104500_extend_quran_public_search_rate_limit');
    }
    public function description(): string
    {
        return 'Allow the single HMAC-backed public Qur’an search rate-limit scope.';
    }
    public function dependencies(): array
    {
        return [(new CreateQuranSearchCorpusMigration())->id()];
    }
    public function up(): array
    {
        return [new SqlMigrationStep(new MigrationStepId('001_extend_scope'), 'Extend the closed rate-limit catalog for public Qur’an search.', "ALTER TABLE identity_rate_limit_buckets DROP CHECK ck_identity_rate_limit_scope, ADD CONSTRAINT ck_identity_rate_limit_scope CHECK (scope IN ('ACCOUNT_REGISTRATION_EMAIL','ACCOUNT_REGISTRATION_PEER','EMAIL_VERIFICATION_RESEND_EMAIL','EMAIL_VERIFICATION_RESEND_PEER','EMAIL_VERIFICATION_ATTEMPT','EMAIL_VERIFICATION_PEER','PASSWORD_AUTHENTICATION_EMAIL','PASSWORD_AUTHENTICATION_PEER','PASSWORD_RECOVERY_REQUEST_EMAIL','PASSWORD_RECOVERY_REQUEST_PEER','PASSWORD_RECOVERY_ATTEMPT','PASSWORD_RECOVERY_ATTEMPT_PEER','MFA_AUTHENTICATION_ACCOUNT','MFA_AUTHENTICATION_PEER','PASSKEY_AUTHENTICATION_PEER','STEP_UP_ACCOUNT','STEP_UP_PEER','PASSKEY_REGISTRATION_ACCOUNT','QURAN_GOVERNANCE_MUTATION_ACCOUNT','QURAN_GOVERNANCE_MUTATION_PEER','QURAN_PUBLIC_SEARCH_PEER'))")];
    }
    public function down(): array
    {
        return [];
    }
    public function reversible(): bool
    {
        return false;
    }
}
