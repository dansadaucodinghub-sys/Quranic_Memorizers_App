<?php

declare(strict_types=1);

namespace Qmdb\Shared\Infrastructure\Persistence\MySql\Connection;

use PDO;
use SensitiveParameter;

interface PdoConnector
{
    /** @param array<int, mixed> $options */
    public function connect(
        string $dsn,
        string $username,
        #[SensitiveParameter] string $password,
        array $options,
    ): PDO;
}
