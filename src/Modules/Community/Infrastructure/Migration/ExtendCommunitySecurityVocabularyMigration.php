<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Infrastructure\Migration;

use Qmdb\Modules\MediaModeration\Infrastructure\Migration\CreateMediaConsentReviewMigration;
use Qmdb\Modules\MediaModeration\Infrastructure\Migration\ExtendMediaGovernanceSecurityMigration;
use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

/** Extend frozen P9 SQL snapshots without making P9 depend on P10 enums. */
final readonly class ExtendCommunitySecurityVocabularyMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260922103000_extend_community_security_vocabulary');
    }
    public function description(): string
    {
        return 'Authorize P10 audit subjects, report limits, and action-bound step-up grants.';
    }
    public function dependencies(): array
    {
        return [(new CreateCommunitySafetyFoundationMigration())->id()];
    }
    public function reversible(): bool
    {
        return false;
    }
    public function down(): array
    {
        return [];
    }

    public function up(): array
    {
        $p9 = (new ExtendMediaGovernanceSecurityMigration())->up();
        $rateLimits = str_replace("'MEDIA_GOVERNANCE_ACCOUNT'", "'MEDIA_GOVERNANCE_ACCOUNT',\n'COMMUNITY_REPORT_ACCOUNT',\n'COMMUNITY_MUTATION_ACCOUNT'", $p9[1]->sql());
        $subjects = str_replace("'MEDIA_ASSET'", "'MEDIA_ASSET',\n'RECITATION_CLIP',\n'COMMUNITY_REPORT',\n'COMMUNITY_MODERATION_CASE'", $p9[2]->sql());
        $stepUp = str_replace("'MEDIA_CONSENT_GRANT'", "'MEDIA_CONSENT_GRANT',\n'CLIP_PUBLISH',\n'COMMUNITY_MODERATION_DECIDE'", (new CreateMediaConsentReviewMigration())->up()[4]->sql());
        return [
            new SqlMigrationStep(new MigrationStepId('001_rate_scopes'), 'Add bounded P10 rate-limit scopes.', $rateLimits),
            new SqlMigrationStep(new MigrationStepId('002_audit_subjects'), 'Add P10 audit subjects.', $subjects),
            new SqlMigrationStep(new MigrationStepId('003_step_up'), 'Add action-bound P10 step-up grants.', $stepUp),
        ];
    }
}
