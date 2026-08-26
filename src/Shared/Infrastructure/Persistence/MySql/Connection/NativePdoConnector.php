<?php

declare(strict_types=1);

namespace Qmdb\Shared\Infrastructure\Persistence\MySql\Connection;

use PDO;
use SensitiveParameter;

final readonly class NativePdoConnector implements PdoConnector
{
    public function connect(
        string $dsn,
        string $username,
        #[SensitiveParameter] string $password,
        array $options,
    ): PDO {
        return new PDO($dsn, $username, $password, $options);
    }
}
