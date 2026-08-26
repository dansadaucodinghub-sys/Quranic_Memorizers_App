<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Migration;

use InvalidArgumentException;

final readonly class SqlMigrationStep
{
    /** @var array<string, bool|float|int|string|null> */
    private array $parameters;

    /** @param array<string, mixed> $parameters */
    public function __construct(
        private MigrationStepId $id,
        private string $description,
        private string $sql,
        array $parameters = [],
    ) {
        SqlStatementPolicy::assertMigrationSql($sql);
        SqlStatementPolicy::assertDescription($description);
        $this->parameters = SqlStatementPolicy::validateParameters($parameters);
    }

    public function id(): MigrationStepId
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
