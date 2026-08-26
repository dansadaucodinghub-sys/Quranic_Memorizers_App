<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\MySql;

use PDO;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Connection\PdoConnector;
use SensitiveParameter;

final class RecordingPdoConnector implements PdoConnector
{
    public int $calls = 0;
    public string $dsn = '';
    public string $username = '';
    public string $password = '';

    /** @var array<int, mixed> */
    public array $options = [];

    public function __construct(private readonly ?\Throwable $failure = null)
    {
    }

    public function connect(
        string $dsn,
        string $username,
        #[SensitiveParameter] string $password,
        array $options,
    ): PDO {
        $this->calls++;
        $this->dsn = $dsn;
        $this->username = $username;
        $this->password = $password;
        $this->options = $options;
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return new PDO('sqlite::memory:');
    }
}
