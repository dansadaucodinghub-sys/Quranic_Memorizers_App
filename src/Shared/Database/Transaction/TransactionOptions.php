<?php

declare(strict_types=1);

namespace Qmdb\Shared\Database\Transaction;

final readonly class TransactionOptions
{
    private function __construct(
        private TransactionIsolation $isolation,
        private bool $readOnly,
        private TransactionRetryPolicy $retryPolicy,
    ) {
    }

    public static function readWrite(
        TransactionIsolation $isolation = TransactionIsolation::READ_COMMITTED,
        ?TransactionRetryPolicy $retryPolicy = null,
    ): self {
        return new self($isolation, false, $retryPolicy ?? new TransactionRetryPolicy(3, 25, 250));
    }

    public static function readOnly(
        TransactionIsolation $isolation = TransactionIsolation::READ_COMMITTED,
        ?TransactionRetryPolicy $retryPolicy = null,
    ): self {
        return new self($isolation, true, $retryPolicy ?? new TransactionRetryPolicy(3, 25, 250));
    }

    public function isolation(): TransactionIsolation
    {
        return $this->isolation;
    }
    public function isReadOnly(): bool
    {
        return $this->readOnly;
    }
    public function retryPolicy(): TransactionRetryPolicy
    {
        return $this->retryPolicy;
    }
}
