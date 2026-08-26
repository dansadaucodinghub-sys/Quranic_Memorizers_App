<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Seed;

use Qmdb\Shared\Schema\Migration\SqlStatementPolicy;

final readonly class SqlSeedStep
{
    /** @var array<string, bool|float|int|string|null> */
    private array $parameters;

    /** @param array<string, mixed> $parameters */
    public function __construct(
        private SeedStepId $id,
        private string $description,
        private string $sql,
        array $parameters = [],
    ) {
        SqlStatementPolicy::assertSeedSql($sql);
        SqlStatementPolicy::assertDescription($description);
        $this->parameters = SqlStatementPolicy::validateParameters($parameters);
    }

    public function id(): SeedStepId
    {
        return $this->id;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function sql(): string
    {
        return $this->sql;
    }

    /** @return array<string, bool|float|int|string|null> */
    public function parameters(): array
    {
        return $this->parameters;
    }
}
