<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

/** Permit one non-archived replacement per source Clip without changing applied P10 DDL. */
final readonly class AddRecitationClipSupersessionConstraintMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260922112000_add_clip_supersession_constraint');
    }

    public function description(): string
    {
        return 'Constrain active Recitation Clip supersession to one replacement per source.';
    }

    public function dependencies(): array
    {
        return [(new AddCommunityFeedKeysetIndexMigration())->id()];
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
        return [new SqlMigrationStep(
            new MigrationStepId('001_active_successor'),
            'Require one non-archived replacement per source Clip.',
            "ALTER TABLE recitation_clips
             ADD COLUMN active_supersedes_clip_id BIGINT UNSIGNED
               GENERATED ALWAYS AS (CASE WHEN status='ARCHIVED' THEN NULL ELSE supersedes_clip_id END) STORED,
             ADD UNIQUE KEY uq_p10_clip_active_supersession(workspace_id,active_supersedes_clip_id)"
        )];
    }
}
