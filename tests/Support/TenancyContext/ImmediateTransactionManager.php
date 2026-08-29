<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\TenancyContext;

use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Database\Transaction\TransactionOptions;

final readonly class ImmediateTransactionManager implements TransactionManager
{
    public function transactional(callable $operation, ?TransactionOptions $options = null): mixed
    {
        return $operation();
    }
}
