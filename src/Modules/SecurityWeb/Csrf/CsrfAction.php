<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityWeb\Csrf;

enum CsrfAction: string
{
    case ACCOUNT_REGISTER = 'account.register';
    case ACCOUNT_EMAIL_VERIFY = 'account.email.verify';
    case ACCOUNT_EMAIL_RESEND = 'account.email.resend';
}
