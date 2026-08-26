<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityWeb\Csrf;

enum CsrfTokenVerificationResult: string
{
    case VALID = 'VALID';
    case INVALID = 'INVALID';
}
