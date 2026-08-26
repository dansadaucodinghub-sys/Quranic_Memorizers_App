<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Migration;

use InvalidArgumentException;

final readonly class MigrationRegistry
{
    /** @var array<string, Migration> */
    private array $byId;
    /** @var list<Migration> */
    private array $ordered;

    /** @param list<Migration> $migrations */
    public function __construct(array $migrations)
    {
        $byId = [];
        foreach ($migrations as $migration) {
            $id = $migration->id()->value();
            if (isset($byId[$id])) {
                throw new InvalidArgumentException('Duplicate migration ID.');
            }
            self::validateMigration($migration);
            $byId[$id] = $migration;
        }
        foreach ($byId as $migration) {
            foreach ($migration->dependencies() as $dependency) {
                if ($dependency->value() === $migration->id()->value()) {
                    throw new InvalidArgumentException('Migration cannot depend on itself.');
                }
                if (!isset($byId[$dependency->value()])) {
                    throw new InvalidArgumentException('Migration dependency is not registered.');
                }
            }
        }
        $this->byId = $byId;
        $this->ordered = self::sort($byId);
    }

    /** @return list<Migration> */
    public function ordered(): array
    {
        return $this->ordered;
    }

    public function find(MigrationId $id): ?Migration
    {
        return $this->byId[$id->value()] ?? null;
    }

    /** @return array<string, Migration> */
    public function byId(): array
    {
        return $this->byId;
    }

    private static function validateMigration(Migration $migration): void
    {
        if (trim($migration->description()) === '' || strlen($migration->description()) > 200) {
            throw new InvalidArgumentException('Migration description is invalid.');
        }
        $dependencies = [];
        foreach ($migration->dependencies() as $dependency) {
            if (isset($dependencies[$dependency->value()])) {
                throw new InvalidArgumentException('Duplicate migration dependency.');
            }
            $dependencies[$dependency->value()] = true;
        }
        $steps = [];
        foreach ($migration->up() as $step) {
            if (isset($steps[$step->id()->value()])) {
                throw new InvalidArgumentException('Duplicate migration step ID.');
            }
            $steps[$step->id()->value()] = true;
        }
        if ($migration->reversible() && $migration->down() === []) {
            throw new InvalidArgumentException('Reversible migration requires down steps.');
        }
        if (!$migration->reversible() && $migration->down() !== []) {
            throw new InvalidArgumentException('Irreversible migration cannot contain down steps.');
        }
    }

    /**
     * @param array<string, Migration> $byId
     * @return list<Migration>
     */
    private static function sort(array $byId): array
    {
        $visiting = [];
        $visited = [];
        $ordered = [];
        $ids = array_keys($byId);
        sort($ids, SORT_STRING);
        foreach ($ids as $id) {
            self::visit($id, $byId, $visiting, $visited, $ordered);
        }

        return $ordered;
    }

    /**
     * @param array<string, Migration> $byId
     * @param array<string, bool> $visiting
     * @param array<string, bool> $visited
     * @param list<Migration> $ordered
     */
    private static function visit(
        string $id,
        array $byId,
        array &$visiting,
        array &$visited,
        array &$ordered,
    ): void {
        if (isset($visited[$id])) {
            return;
        }
        if (isset($visiting[$id])) {
            throw new InvalidArgumentException('Migration dependency cycle detected.');
        }
        $visiting[$id] = true;
        $dependencies = $byId[$id]->dependencies();
        usort($dependencies, static fn (MigrationId $a, MigrationId $b): int => $a->value() <=> $b->value());
        foreach ($dependencies as $dependency) {
            self::visit($dependency->value(), $byId, $visiting, $visited, $ordered);
        }
        unset($visiting[$id]);
        $visited[$id] = true;
        $ordered[] = $byId[$id];
    }
}
