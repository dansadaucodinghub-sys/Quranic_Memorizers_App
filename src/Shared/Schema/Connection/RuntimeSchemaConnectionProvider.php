<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Connection;

use PDO;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;

final readonly class RuntimeSchemaConnectionProvider implements SchemaConnectionProvider
{
    public function __construct(private DatabaseConnectionProvider $provider)
    {
    }

    public function connection(): PDO
    {
        return $this->provider->connection();
    }
}
