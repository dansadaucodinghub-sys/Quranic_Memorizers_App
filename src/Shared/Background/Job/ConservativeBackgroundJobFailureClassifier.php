<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Job;

use Qmdb\Shared\Observability\Error\ExceptionFingerprint;
use Throwable;

final readonly class ConservativeBackgroundJobFailureClassifier implements BackgroundJobFailureClassifier
{
    public function __construct(private ExceptionFingerprint $fingerprint)
    {
    }

    public function classify(Throwable $failure): BackgroundJobFailure
    {
        $retryable = $failure instanceof RetryableBackgroundJobFailure;
        $code = $retryable ? 'JOB_RETRYABLE_FAILURE' : 'JOB_PERMANENT_FAILURE';

        return new BackgroundJobFailure(
            new BackgroundJobFailureCode($code),
            $failure::class,
            $this->fingerprint->forThrowable($failure),
            $retryable,
        );
    }
}
