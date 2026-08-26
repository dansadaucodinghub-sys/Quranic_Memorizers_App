<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Domain;

use RuntimeException;

final class DuplicateLoginSubmissionException extends RuntimeException
{
}
