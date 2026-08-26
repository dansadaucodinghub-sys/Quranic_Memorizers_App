<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Security\Password;

enum PasswordPolicyViolation: string
{
    case EMPTY = 'EMPTY';
    case TOO_SHORT = 'TOO_SHORT';
    case TOO_LONG = 'TOO_LONG';
    case NUL = 'NUL';
    case WHITESPACE_ONLY = 'WHITESPACE_ONLY';
    case INVALID_UTF8 = 'INVALID_UTF8';
    case CONFIRMATION_MISMATCH = 'CONFIRMATION_MISMATCH';
}
