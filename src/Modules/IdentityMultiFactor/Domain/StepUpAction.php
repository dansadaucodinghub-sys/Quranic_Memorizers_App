<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Domain;

enum StepUpAction: string
{
    case MFA_ENROLL_TOTP = 'MFA_ENROLL_TOTP';
    case MFA_REGISTER_PASSKEY = 'MFA_REGISTER_PASSKEY';
    case MFA_ENABLE = 'MFA_ENABLE';
    case MFA_DISABLE = 'MFA_DISABLE';
    case MFA_REGENERATE_RECOVERY_CODES = 'MFA_REGENERATE_RECOVERY_CODES';
    case MFA_REVOKE_TOTP = 'MFA_REVOKE_TOTP';
    case MFA_REVOKE_PASSKEY = 'MFA_REVOKE_PASSKEY';

    public function requirement(): AuthenticationAssuranceLevel
    {
        return match ($this) {
            self::MFA_ENROLL_TOTP, self::MFA_REGISTER_PASSKEY => AuthenticationAssuranceLevel::PRIMARY,
            default => AuthenticationAssuranceLevel::MULTI_FACTOR,
        };
    }

    public function continuation(): string
    {
        return match ($this) {
            self::MFA_ENROLL_TOTP => '/account/security/mfa/totp/enroll',
            self::MFA_REGISTER_PASSKEY => '/account/security/passkeys/register',
            self::MFA_DISABLE => '/account/security/mfa/disable',
            default => '/account/security/authentication',
        };
    }
}
