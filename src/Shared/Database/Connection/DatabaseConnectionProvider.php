<?php

declare(strict_types=1);

namespace Qmdb\Shared\Database\Connection;

use PDO;

interface DatabaseConnectionProvider
{
    public function connection(): PDO;
}
