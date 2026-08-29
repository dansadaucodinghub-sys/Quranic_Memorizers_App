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
    case ACCOUNT_WORKSPACE_SWITCH = 'account.workspace.switch';
    case ACCOUNT_WORKSPACE_CLEAR = 'account.workspace.clear';
    case ACCOUNT_PASSWORD_RECOVERY_REQUEST = 'account.password_recovery.request';
    case ACCOUNT_PASSWORD_RECOVERY_RESET = 'account.password_recovery.reset';
    case ACCOUNT_MFA_LOGIN_TOTP = 'account.mfa.login.totp';
    case ACCOUNT_MFA_LOGIN_RECOVERY_CODE = 'account.mfa.login.recovery_code';
    case ACCOUNT_MFA_LOGIN_PASSKEY = 'account.mfa.login.passkey';
    case ACCOUNT_PASSKEY_LOGIN = 'account.passkey.login';
    case ACCOUNT_STEP_UP_PASSWORD = 'account.step_up.password';
    case ACCOUNT_STEP_UP_TOTP = 'account.step_up.totp';
    case ACCOUNT_STEP_UP_RECOVERY_CODE = 'account.step_up.recovery_code';
    case ACCOUNT_STEP_UP_PASSKEY = 'account.step_up.passkey';
    case ACCOUNT_MFA_TOTP_ENROLL = 'account.mfa.totp.enroll';
    case ACCOUNT_MFA_TOTP_CONFIRM = 'account.mfa.totp.confirm';
    case ACCOUNT_MFA_TOTP_REVOKE = 'account.mfa.totp.revoke';
    case ACCOUNT_PASSKEY_REGISTER = 'account.passkey.register';
    case ACCOUNT_PASSKEY_REVOKE = 'account.passkey.revoke';
    case ACCOUNT_MFA_ENABLE = 'account.mfa.enable';
    case ACCOUNT_MFA_DISABLE = 'account.mfa.disable';
    case ACCOUNT_MFA_RECOVERY_CODES_REGENERATE = 'account.mfa.recovery_codes.regenerate';
}
