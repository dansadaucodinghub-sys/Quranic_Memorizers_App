<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

/** Adds the explicit, replay-safe duplicate-review operation without rewriting an applied migration. */
final readonly class ExtendIdentityResolutionReviewIdempotencyMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260902050600_extend_identity_resolution_review_idempotency');
    }

    public function description(): string
    {
        return 'Add replay-safe duplicate-review idempotency to the closed identity catalog.';
    }

    public function dependencies(): array
    {
        return [(new ExtendIdentityResolutionSecurityCatalogMigration())->id()];
    }

    public function up(): array
    {
        return [new SqlMigrationStep(new MigrationStepId('001_extend_idempotency_operations'), 'Allow private duplicate-review idempotency.', <<<'SQL'
ALTER TABLE identity_idempotency_records DROP CHECK ck_identity_idempotency_operation,
 ADD CONSTRAINT ck_identity_idempotency_operation CHECK (operation IN (
 'ACCOUNT_REGISTRATION','EMAIL_VERIFICATION_RESEND','PASSWORD_RECOVERY_REQUEST','PASSWORD_RECOVERY_RESET',
 'PERSON_PROFILE_CREATE','PERSON_PROFILE_UPDATE','PERSON_ROLE_ACTIVATE','PERSON_ROLE_DEACTIVATE','MEMORIZER_PROGRESS_UPDATE','DEPENDENT_PROFILE_CREATE','DEPENDENT_PROFILE_UPDATE','GUARDIANSHIP_REVOKE',
 'ORGANIZATION_CREATE','ORGANIZATION_UPDATE','ORGANIZATION_RETIRE','ORGANIZATION_UNIT_CREATE','ORGANIZATION_UNIT_UPDATE','ORGANIZATION_UNIT_RETIRE',
 'ORGANIZATION_AFFILIATION_REQUEST','ORGANIZATION_AFFILIATION_ACCEPT','ORGANIZATION_AFFILIATION_DECLINE','ORGANIZATION_AFFILIATION_WITHDRAW','ORGANIZATION_AFFILIATION_ASSIGNMENTS_UPDATE','ORGANIZATION_AFFILIATION_SUSPEND','ORGANIZATION_AFFILIATION_RESUME','ORGANIZATION_AFFILIATION_END','ORGANIZATION_AFFILIATION_LEAVE',
 'PROFILE_CLAIM_PAIRING_CREATE','PROFILE_CLAIM_PAIRING_REVOKE','PROFILE_CLAIM_AUTHORIZE_GUARDIAN','PROFILE_CLAIM_AUTHORIZE_PLATFORM','PROFILE_CLAIM_ACCEPT','PROFILE_CLAIM_DECLINE','PROFILE_CLAIM_REVOKE','PROFILE_VERIFICATION_RECORD','PROFILE_VERIFICATION_REVOKE','PROFILE_DUPLICATE_REPORT','PROFILE_DUPLICATE_CONSENT','PROFILE_DUPLICATE_DISMISS','PROFILE_DUPLICATE_RESOLVE','PROFILE_DUPLICATE_REVIEW'
 ))
SQL)];
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
