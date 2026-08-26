<?php

declare(strict_types=1);

namespace Qmdb\Shared\Infrastructure\Persistence\MySql\Connection;

use PDO;

final readonly class MySqlSessionInitializer
{
    public function initialize(PDO $connection): void
    {
        $connection->exec("SET SESSION time_zone = '+00:00'");
        $connection->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
        $connection->exec(
            "SET SESSION sql_mode = CONCAT_WS(',', @@SESSION.sql_mode, "
            . "'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION,ONLY_FULL_GROUP_BY')",
        );
    }
}
