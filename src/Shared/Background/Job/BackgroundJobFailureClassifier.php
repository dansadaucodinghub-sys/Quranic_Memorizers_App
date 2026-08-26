<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Job;

use Throwable;

interface BackgroundJobFailureClassifier
{
    public function classify(Throwable $failure): BackgroundJobFailure;
}
