<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

/** Add a global, deterministic published-Clip keyset access path. */
final readonly class AddCommunityFeedKeysetIndexMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260922111000_add_community_feed_keyset_index');
    }

    public function description(): string
    {
        return 'Index public Clip publication order for bounded feed scans.';
    }

    public function dependencies(): array
    {
        return [(new CreateCommunityInteractionEventMigration())->id()];
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
            new MigrationStepId('001_feed_keyset_index'),
            'Index visible Clip publication order without changing prior Clip records.',
            'ALTER TABLE recitation_clips ADD KEY ix_p10_clip_public_feed(status,audience,published_at,id)'
        )];
    }
}
