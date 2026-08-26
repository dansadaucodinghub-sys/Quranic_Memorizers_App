<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Schema;

use PDO;
use Qmdb\Shared\Schema\Connection\SchemaConnectionProvider;

final readonly class PdoSchemaConnectionProvider implements SchemaConnectionProvider
{
    public function __construct(private PDO $connection)
    {
    }

    public function connection(): PDO
    {
        return $this->connection;
    }
}
