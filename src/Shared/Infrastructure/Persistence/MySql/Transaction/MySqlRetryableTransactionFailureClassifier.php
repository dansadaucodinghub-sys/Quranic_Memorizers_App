<?php

declare(strict_types=1);

namespace Qmdb\Shared\Infrastructure\Persistence\MySql\Transaction;

use PDOException;
use Throwable;

final readonly class MySqlRetryableTransactionFailureClassifier
{
    public function isRetryable(Throwable $failure): bool
    {
        $current = $failure;
        do {
            if ($current instanceof PDOException) {
                if ((string) $current->getCode() === '40001') {
                    return true;
                }
                $driverCode = $current->errorInfo[1] ?? null;
                if ($driverCode === 1213 || $driverCode === '1213') {
                    return true;
                }
            }
            $current = $current->getPrevious();
        } while ($current !== null);

        return false;
    }
}
