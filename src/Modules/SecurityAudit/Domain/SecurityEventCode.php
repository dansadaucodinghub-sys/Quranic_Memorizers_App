<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Domain;

use InvalidArgumentException;

enum SecurityEventCode: string
{
    case ACCOUNT_SUSPENDED = 'identity.account.suspended';
    case ACCOUNT_REACTIVATED = 'identity.account.reactivated';
    case PASSWORD_RESET_COMPLETED = 'identity.password_reset.completed';
    case MFA_ENABLED = 'identity.mfa.enabled';
    case MFA_DISABLED = 'identity.mfa.disabled';
    case TOTP_ADDED = 'identity.totp.added';
    case TOTP_REMOVED = 'identity.totp.removed';
    case PASSKEY_ADDED = 'identity.passkey.added';
    case PASSKEY_REMOVED = 'identity.passkey.removed';
    case PASSKEY_SUSPENDED = 'identity.passkey.suspended';
    case RECOVERY_CODE_USED = 'identity.recovery_code.used';
    case RECOVERY_CODES_REGENERATED = 'identity.recovery_codes.regenerated';
    case SESSION_REMOTE_REVOKED = 'identity.session.remote_revoked';
    case DEVICE_REVOKED = 'identity.device.revoked';
    case PLATFORM_ROLE_ASSIGNED = 'authorization.platform_role.assigned';
    case PLATFORM_ROLE_REVOKED = 'authorization.platform_role.revoked';
    case WORKSPACE_ROLE_ASSIGNED = 'authorization.workspace_role.assigned';
    case WORKSPACE_ROLE_REVOKED = 'authorization.workspace_role.revoked';
    case TEMPORARY_ACTIVATED = 'privileged_access.temporary.activated';
    case TEMPORARY_ENDED = 'privileged_access.temporary.ended';
    case TEMPORARY_REVOKED = 'privileged_access.temporary.revoked';
    case TEMPORARY_EXPIRED = 'privileged_access.temporary.expired';
    case SUPPORT_ACTIVATED = 'privileged_access.support.activated';
    case SUPPORT_ENDED = 'privileged_access.support.ended';
    case SUPPORT_REVOKED = 'privileged_access.support.revoked';
    case SUPPORT_EXPIRED = 'privileged_access.support.expired';
    case SUPPORT_REVIEW_COMPLETED = 'privileged_access.support.review_completed';
    case BREAK_GLASS_ACTIVATED = 'privileged_access.break_glass.activated';
    case BREAK_GLASS_ENDED = 'privileged_access.break_glass.ended';
    case BREAK_GLASS_REVOKED = 'privileged_access.break_glass.revoked';
    case BREAK_GLASS_EXPIRED = 'privileged_access.break_glass.expired';
    case BREAK_GLASS_REVIEW_COMPLETED = 'privileged_access.break_glass.review_completed';
    case PERSON_PROFILE_CREATED = 'people.profile.created';
    case PERSON_PROFILE_UPDATED = 'people.profile.updated';
    case PERSON_ROLE_ACTIVATED = 'people.role.activated';
    case PERSON_ROLE_DEACTIVATED = 'people.role.deactivated';
    case MEMORIZER_PROGRESS_UPDATED = 'people.memorizer_progress.updated';
    case DEPENDENT_PROFILE_CREATED = 'people.dependent.created';
    case GUARDIANSHIP_REVOKED = 'people.guardianship.revoked';
    case ORGANIZATION_CREATED = 'organizations.organization.created';
    case ORGANIZATION_UPDATED = 'organizations.organization.updated';
    case ORGANIZATION_RETIRED = 'organizations.organization.retired';
    case ORGANIZATION_UNIT_CREATED = 'organizations.unit.created';
    case ORGANIZATION_UNIT_UPDATED = 'organizations.unit.updated';
    case ORGANIZATION_UNIT_RETIRED = 'organizations.unit.retired';

    public function severity(): SecurityEventSeverity
    {
        if ($this === self::ACCOUNT_SUSPENDED || $this === self::BREAK_GLASS_ACTIVATED) {
            return SecurityEventSeverity::CRITICAL;
        }

        return SecurityEventSeverity::WARNING;
    }

    public static function fromTrustedString(string $value): self
    {
        if (preg_match('/\\A[a-z][a-z0-9_]*(?:\\.[a-z][a-z0-9_]*)+\\z/', $value) !== 1) {
            throw new InvalidArgumentException('Security audit event code is invalid.');
        }

        return self::from($value);
    }
}
