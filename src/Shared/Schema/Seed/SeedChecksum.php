<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Seed;

use Qmdb\Shared\Schema\Checksum\CanonicalChecksum;

final readonly class SeedChecksum
{
    public function __construct(private CanonicalChecksum $checksum)
    {
    }

    public function binary(Seed $seed): string
    {
        return $this->checksum->binary([
            'id' => $seed->id()->value(),
            'description' => $seed->description(),
            'dependencies' => array_map(static fn (SeedId $id): string => $id->value(), $seed->dependencies()),
            'steps' => array_map(fn (SqlSeedStep $step): array => [
                'id' => $step->id()->value(),
                'description' => $step->description(),
                'sql' => $this->checksum->normalizeSql($step->sql()),
                'parameters' => $step->parameters(),
            ], $seed->steps()),
        ]);
    }

    public function hexadecimal(Seed $seed): string
    {
        return bin2hex($this->binary($seed));
    }
}
