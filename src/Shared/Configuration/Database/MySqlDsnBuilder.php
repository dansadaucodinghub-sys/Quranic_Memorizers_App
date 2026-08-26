<?php

declare(strict_types=1);

namespace Qmdb\Shared\Configuration\Database;

final readonly class MySqlDsnBuilder
{
    public function build(DatabaseConfiguration $configuration): string
    {
        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $configuration->host(),
            $configuration->port(),
            $configuration->databaseName(),
        );
    }
}
