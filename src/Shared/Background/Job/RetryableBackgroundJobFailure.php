<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Job;

use RuntimeException;

class RetryableBackgroundJobFailure extends RuntimeException
{
}
