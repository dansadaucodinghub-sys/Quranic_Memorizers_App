<?php

declare(strict_types=1);

namespace Qmdb\Shared\Database\Transaction;

interface TransactionManager
{
    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function transactional(callable $operation, ?TransactionOptions $options = null): mixed;
}
