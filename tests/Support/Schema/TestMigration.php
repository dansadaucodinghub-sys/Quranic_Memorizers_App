<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Schema;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class TestMigration implements Migration
{
    /**
     * @param list<MigrationId> $dependencies
     * @param list<SqlMigrationStep> $up
     * @param list<SqlMigrationStep> $down
     */
    public function __construct(
        private MigrationId $id,
        private string $description,
        private array $dependencies = [],
        private array $up = [],
        private array $down = [],
        private bool $reversible = false,
    ) {
    }

    public function id(): MigrationId
    {
        return $this->id;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function dependencies(): array
    {
        return $this->dependencies;
    }

    public function up(): array
    {
        return $this->up;
    }

    public function down(): array
    {
        return $this->down;
    }

    public function reversible(): bool
    {
        return $this->reversible;
    }
}
