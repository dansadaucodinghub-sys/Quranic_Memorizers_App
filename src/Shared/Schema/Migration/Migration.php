<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Migration;

interface Migration
{
    public function id(): MigrationId;

    public function description(): string;

    /** @return list<MigrationId> */
    public function dependencies(): array;

    /** @return list<SqlMigrationStep> */
    public function up(): array;

    /** @return list<SqlMigrationStep> */
    public function down(): array;

    public function reversible(): bool;
}
