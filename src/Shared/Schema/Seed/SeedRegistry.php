<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Seed;

use InvalidArgumentException;

final readonly class SeedRegistry
{
    /** @var array<string, Seed> */
    private array $byId;
    /** @var list<Seed> */
    private array $ordered;

    /** @param list<Seed> $seeds */
    public function __construct(array $seeds)
    {
        $byId = [];
        foreach ($seeds as $seed) {
            $id = $seed->id()->value();
            if (isset($byId[$id])) {
                throw new InvalidArgumentException('Duplicate seed ID.');
            }
            if (trim($seed->description()) === '') {
                throw new InvalidArgumentException('Seed description is invalid.');
            }
            $stepIds = [];
            foreach ($seed->steps() as $step) {
                if (isset($stepIds[$step->id()->value()])) {
                    throw new InvalidArgumentException('Duplicate seed step ID.');
                }
                $stepIds[$step->id()->value()] = true;
            }
            $byId[$id] = $seed;
        }
        foreach ($byId as $seed) {
            $seenDependencies = [];
            foreach ($seed->dependencies() as $dependency) {
                if ($dependency->value() === $seed->id()->value()) {
                    throw new InvalidArgumentException('Seed cannot depend on itself.');
                }
                if (isset($seenDependencies[$dependency->value()]) || !isset($byId[$dependency->value()])) {
                    throw new InvalidArgumentException('Seed dependency is invalid.');
                }
                $seenDependencies[$dependency->value()] = true;
            }
        }
        $this->byId = $byId;
        $this->ordered = self::sort($byId);
    }

    /** @return list<Seed> */
    public function ordered(): array
    {
        return $this->ordered;
    }

    public function find(SeedId $id): ?Seed
    {
        return $this->byId[$id->value()] ?? null;
    }

    /** @return array<string, Seed> */
    public function byId(): array
    {
        return $this->byId;
    }

    /**
     * @param array<string, Seed> $byId
     * @return list<Seed>
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
     * @param array<string, Seed> $byId
     * @param array<string, bool> $visiting
     * @param array<string, bool> $visited
     * @param list<Seed> $ordered
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
            throw new InvalidArgumentException('Seed dependency cycle detected.');
        }
        $visiting[$id] = true;
        $dependencies = $byId[$id]->dependencies();
        usort($dependencies, static fn (SeedId $a, SeedId $b): int => $a->value() <=> $b->value());
        foreach ($dependencies as $dependency) {
            self::visit($dependency->value(), $byId, $visiting, $visited, $ordered);
        }
        unset($visiting[$id]);
        $visited[$id] = true;
        $ordered[] = $byId[$id];
    }
}
