<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityWeb\Csrf;

enum CsrfAction: string
{
    case ACCOUNT_REGISTER = 'account.register';
    case ACCOUNT_EMAIL_VERIFY = 'account.email.verify';
    case ACCOUNT_EMAIL_RESEND = 'account.email.resend';
    case ACCOUNT_LOGIN = 'account.login';
    case ACCOUNT_LOGOUT = 'account.logout';
    case ACCOUNT_SESSION_REVOKE = 'account.session.revoke';
    case ACCOUNT_DEVICE_REVOKE = 'account.device.revoke';
}
