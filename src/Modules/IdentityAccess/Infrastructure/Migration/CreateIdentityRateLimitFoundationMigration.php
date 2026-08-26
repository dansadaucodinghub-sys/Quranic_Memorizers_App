<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreateIdentityRateLimitFoundationMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260826010600_create_identity_rate_limit_foundation');
    }

    public function description(): string
    {
        return 'Create database-backed identity rate-limit buckets.';
    }

    public function dependencies(): array
    {
        return [(new CreateIdentityVerificationFoundationMigration())->id()];
    }

    public function up(): array
    {
        return [new SqlMigrationStep(
            new MigrationStepId('001_create_identity_rate_limit_buckets'),
            'Create identity rate-limit buckets.',
            <<<'SQL'
CREATE TABLE identity_rate_limit_buckets (
    scope VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    bucket_hash BINARY(32) NOT NULL,
    window_started_at DATETIME(6) NOT NULL,
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    blocked_until DATETIME(6) NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (scope, bucket_hash),
    KEY ix_identity_rate_limit_blocked_scope (blocked_until, scope),
    KEY ix_identity_rate_limit_updated_scope (updated_at, scope),
    CONSTRAINT ck_identity_rate_limit_scope CHECK (scope IN (
        'ACCOUNT_REGISTRATION_EMAIL','ACCOUNT_REGISTRATION_PEER',
        'EMAIL_VERIFICATION_RESEND_EMAIL','EMAIL_VERIFICATION_RESEND_PEER',
        'EMAIL_VERIFICATION_ATTEMPT','EMAIL_VERIFICATION_PEER',
        'PASSWORD_AUTHENTICATION_EMAIL','PASSWORD_AUTHENTICATION_PEER'
    )),
    CONSTRAINT ck_identity_rate_limit_version CHECK (version >= 1),
    CONSTRAINT ck_identity_rate_limit_blocked CHECK (
        blocked_until IS NULL OR blocked_until >= window_started_at
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
        )];
    }

    public function down(): array
    {
        return [new SqlMigrationStep(
            new MigrationStepId('001_drop_identity_rate_limit_buckets'),
            'Drop identity rate-limit buckets.',
            'DROP TABLE identity_rate_limit_buckets',
        )];
    }

    public function reversible(): bool
    {
        return true;
    }
}
