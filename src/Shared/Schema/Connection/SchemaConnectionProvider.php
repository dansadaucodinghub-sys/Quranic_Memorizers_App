<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Connection;

use PDO;

interface SchemaConnectionProvider
{
    public function connection(): PDO;
}
