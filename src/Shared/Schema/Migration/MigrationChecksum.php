<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Migration;

use Qmdb\Shared\Schema\Checksum\CanonicalChecksum;

final readonly class MigrationChecksum
{
    public function __construct(private CanonicalChecksum $checksum)
    {
    }

    public function stepBinary(SqlMigrationStep $step): string
    {
        return $this->checksum->binary($this->stepPayload($step));
    }

    public function migrationBinary(Migration $migration): string
    {
        return $this->checksum->binary([
            'id' => $migration->id()->value(),
            'description' => $migration->description(),
            'dependencies' => array_map(
                static fn (MigrationId $id): string => $id->value(),
                $migration->dependencies(),
            ),
            'reversible' => $migration->reversible(),
            'up' => array_map(
                fn (SqlMigrationStep $step): string => bin2hex($this->stepBinary($step)),
                $migration->up(),
            ),
            'down' => array_map(
                fn (SqlMigrationStep $step): string => bin2hex($this->stepBinary($step)),
                $migration->down(),
            ),
        ]);
    }

    public function migrationHex(Migration $migration): string
    {
        return bin2hex($this->migrationBinary($migration));
    }

    /** @return array<string, mixed> */
    private function stepPayload(SqlMigrationStep $step): array
    {
        return [
            'id' => $step->id()->value(),
            'description' => $step->description(),
            'sql' => $this->checksum->normalizeSql($step->sql()),
            'parameters' => $step->parameters(),
        ];
    }
}
