<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Job;

final readonly class BackgroundJobFailure
{
    public function __construct(
        private BackgroundJobFailureCode $code,
        private string $exceptionClass,
        private string $exceptionFingerprint,
        private bool $retryable,
    ) {
    }

    public function code(): BackgroundJobFailureCode
    {
        return $this->code;
    }

    public function exceptionClass(): string
    {
        return $this->exceptionClass;
    }

    public function exceptionFingerprint(): string
    {
        return $this->exceptionFingerprint;
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }
}
