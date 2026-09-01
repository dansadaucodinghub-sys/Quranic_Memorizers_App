<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Routing\Security;

use LogicException;

/**
 * Closed production-route policy inventory. New routes must be explicitly
 * classified here before the security verifier can pass.
 */
final class ProductionRouteSecurityPolicyCatalog
{
    /** @var list<string> */
    private const array PUBLIC = [
        'system.home', 'system.about.page', 'system.status.page', 'system.health.live', 'system.health.ready',
        'api.v1.system.about', 'account.registration.form', 'account.registration.submit',
        'account.registration.accepted', 'account.email_verification.resend.form',
        'account.email_verification.resend.submit', 'account.email_verification.completed',
        'account.email_verification.form', 'account.email_verification.submit', 'account.login.form',
        'account.login.submit', 'account.password_recovery.request.form',
        'account.password_recovery.request.submit', 'account.password_recovery.request.accepted',
        'account.password_recovery.reset.form', 'account.password_recovery.reset.submit',
        'account.password_recovery.reset.completed', 'account.mfa.login.form', 'account.mfa.login.totp',
        'account.mfa.login.recovery_code', 'account.mfa.login.passkey.options',
        'account.mfa.login.passkey.verify', 'account.passkey.login.options', 'account.passkey.login.verify',
        'geography.nigeria.index', 'geography.nigeria.area', 'geography.lookup.children',
    ];

    /** @var list<string> */
    private const array AUTHENTICATED = [
        'account.logout', 'account.workspaces.index', 'account.workspaces.switch', 'account.workspaces.clear',
        'account.security.events', 'account.privileged_access.index',
        'account.privileged_access.temporary.request.form', 'account.privileged_access.temporary.request.submit',
        'account.privileged_access.support.request.form', 'account.privileged_access.support.request.submit',
        'account.privileged_access.detail', 'account.privileged_access.approve.form',
        'account.privileged_access.approve.submit', 'account.privileged_access.reject.submit',
        'account.privileged_access.cancel.submit', 'account.privileged_access.activate.submit',
        'account.privileged_access.revoke.submit', 'account.privileged_access.active.end.submit',
        'account.privileged_access.break_glass.activate.form',
        'account.privileged_access.break_glass.activate.submit', 'account.privileged_access.review.form',
        'account.privileged_access.review.submit', 'account.security.sessions',
        'account.security.session_revoke.form', 'account.security.session_revoke.submit',
        'account.security.device_revoke.form', 'account.security.device_revoke.submit', 'account.step_up.form',
        'account.step_up.password', 'account.step_up.totp', 'account.step_up.recovery_code',
        'account.step_up.passkey.options', 'account.step_up.passkey.verify', 'account.security.authentication',
        'account.mfa.totp.enroll.form', 'account.mfa.totp.enroll.submit', 'account.mfa.totp.confirm',
        'account.mfa.totp.qr', 'account.mfa.totp.revoke.form', 'account.mfa.totp.revoke.submit',
        'account.passkey.register.form', 'account.passkey.register.options', 'account.passkey.register.verify',
        'account.passkey.revoke.form', 'account.passkey.revoke.submit', 'account.mfa.enable',
        'account.mfa.disable.form', 'account.mfa.disable.submit', 'account.mfa.recovery_codes.status',
        'account.mfa.recovery_codes.regenerate',
        'account.person_profile.view', 'account.person_profile.create.form', 'account.person_profile.create.submit',
        'account.person_profile.edit.form', 'account.person_profile.update.submit', 'account.person_profile.role.activate',
        'account.person_profile.role.deactivate.form', 'account.person_profile.role.deactivate.submit',
        'account.person_profile.memorizer_progress.form', 'account.person_profile.memorizer_progress.submit',
        'account.person_profile.dependents.index', 'account.person_profile.dependent.create.form',
        'account.person_profile.dependent.create.submit', 'account.person_profile.dependent.view',
        'account.person_profile.dependent.edit.form', 'account.person_profile.dependent.update.submit',
        'account.person_profile.dependent.memorizer_progress.form', 'account.person_profile.dependent.memorizer_progress.submit',
        'account.person_profile.guardianship.revoke.form', 'account.person_profile.guardianship.revoke.submit',
    ];

    /** @var list<string> */
    private const array TENANT_REQUIRED = [
        'workspace.current', 'workspace.privileged_access.approve.form',
        'workspace.privileged_access.approve.submit',
    ];

    /** @var array<string, string> */
    private const array BASE_ROLE_PERMISSIONS = [
        'platform.security.accounts.detail' => 'platform.accounts.view',
        'platform.security.accounts.suspend' => 'platform.accounts.suspend',
        'platform.security.accounts.reactivate' => 'platform.accounts.reactivate',
        'platform.security.events' => 'platform.security_events.view',
        'platform.security.events.detail' => 'platform.security_events.view',
        'platform.security.audit' => 'platform.audit.verify',
        'workspace.organizations.index' => 'workspace.organizations.view',
        'workspace.organizations.create.form' => 'workspace.organizations.manage',
        'workspace.organizations.create.submit' => 'workspace.organizations.manage',
        'workspace.organizations.view' => 'workspace.organizations.view',
        'workspace.organizations.edit.form' => 'workspace.organizations.manage',
        'workspace.organizations.update.submit' => 'workspace.organizations.manage',
        'workspace.organizations.retire.form' => 'workspace.organizations.manage',
        'workspace.organizations.retire.submit' => 'workspace.organizations.manage',
        'workspace.organizations.units.create.form' => 'workspace.organization_units.manage',
        'workspace.organizations.units.create.submit' => 'workspace.organization_units.manage',
        'workspace.organizations.units.view' => 'workspace.organization_units.view',
        'workspace.organizations.units.edit.form' => 'workspace.organization_units.manage',
        'workspace.organizations.units.update.submit' => 'workspace.organization_units.manage',
        'workspace.organizations.units.retire.form' => 'workspace.organization_units.manage',
        'workspace.organizations.units.retire.submit' => 'workspace.organization_units.manage',
    ];

    /** @var array<string, string> */
    private const array STEP_UP_ACTIONS = [
        'platform.security.accounts.suspend' => 'ACCOUNT_SUSPEND',
        'platform.security.accounts.reactivate' => 'ACCOUNT_REACTIVATE',
        'account.privileged_access.approve.submit' => 'TEMPORARY_PRIVILEGE_APPROVE',
        'workspace.privileged_access.approve.submit' => 'SUPPORT_ACCESS_WORKSPACE_APPROVE',
        'account.privileged_access.activate.submit' => 'SUPPORT_ACCESS_ACTIVATE',
        'account.privileged_access.revoke.submit' => 'TEMPORARY_PRIVILEGE_REVOKE',
        'account.privileged_access.break_glass.activate.submit' => 'BREAK_GLASS_ACTIVATE',
        'account.privileged_access.review.submit' => 'SUPPORT_ACCESS_REVIEW',
        'account.mfa.totp.enroll.submit' => 'MFA_ENROLL_TOTP',
        'account.mfa.totp.revoke.submit' => 'MFA_REVOKE_TOTP',
        'account.passkey.register.verify' => 'MFA_REGISTER_PASSKEY',
        'account.passkey.revoke.submit' => 'MFA_REVOKE_PASSKEY',
        'account.mfa.enable' => 'MFA_ENABLE',
        'account.mfa.disable.submit' => 'MFA_DISABLE',
        'account.mfa.recovery_codes.regenerate' => 'MFA_REGENERATE_RECOVERY_CODES',
        'account.person_profile.update.submit' => 'PERSON_PROFILE_SENSITIVE_UPDATE',
        'account.person_profile.dependent.update.submit' => 'PERSON_PROFILE_SENSITIVE_UPDATE',
        'account.person_profile.dependent.create.submit' => 'DEPENDENT_PROFILE_CREATE',
        'account.person_profile.guardianship.revoke.submit' => 'GUARDIANSHIP_REVOKE',
        'workspace.organizations.retire.submit' => 'ORGANIZATION_RETIRE',
        'workspace.organizations.units.retire.submit' => 'ORGANIZATION_UNIT_RETIRE',
    ];

    /** @var array<string, string> */
    private const array CSRF_ACTIONS = [
        'account.registration.submit' => 'account.register',
        'account.email_verification.resend.submit' => 'account.email.resend',
        'account.email_verification.submit' => 'account.email.verify',
        'account.login.submit' => 'account.login',
        'account.logout' => 'account.logout',
        'account.workspaces.switch' => 'account.workspace.switch',
        'account.workspaces.clear' => 'account.workspace.clear',
        'platform.security.accounts.suspend' => 'account.state.suspend',
        'platform.security.accounts.reactivate' => 'account.state.reactivate',
        'account.privileged_access.temporary.request.submit' => 'privileged_access.temporary.request',
        'account.privileged_access.support.request.submit' => 'privileged_access.support.request',
        'account.privileged_access.approve.submit' => 'privileged_access.approve',
        'account.privileged_access.reject.submit' => 'privileged_access.reject',
        'account.privileged_access.cancel.submit' => 'privileged_access.cancel',
        'account.privileged_access.activate.submit' => 'privileged_access.activate',
        'account.privileged_access.revoke.submit' => 'privileged_access.revoke',
        'workspace.privileged_access.approve.submit' => 'privileged_access.approve',
        'account.privileged_access.active.end.submit' => 'privileged_access.end',
        'account.privileged_access.break_glass.activate.submit' => 'privileged_access.break_glass.activate',
        'account.privileged_access.review.submit' => 'privileged_access.review',
        'account.security.session_revoke.submit' => 'account.session.revoke',
        'account.security.device_revoke.submit' => 'account.device.revoke',
        'account.password_recovery.request.submit' => 'account.password_recovery.request',
        'account.password_recovery.reset.submit' => 'account.password_recovery.reset',
        'account.mfa.login.totp' => 'account.mfa.login.totp',
        'account.mfa.login.recovery_code' => 'account.mfa.login.recovery_code',
        'account.mfa.login.passkey.options' => 'account.mfa.login.passkey',
        'account.mfa.login.passkey.verify' => 'account.mfa.login.passkey',
        'account.passkey.login.options' => 'account.passkey.login',
        'account.passkey.login.verify' => 'account.passkey.login',
        'account.step_up.password' => 'account.step_up.password',
        'account.step_up.totp' => 'account.step_up.totp',
        'account.step_up.recovery_code' => 'account.step_up.recovery_code',
        'account.step_up.passkey.options' => 'account.step_up.passkey',
        'account.step_up.passkey.verify' => 'account.step_up.passkey',
        'account.mfa.totp.enroll.submit' => 'account.mfa.totp.enroll',
        'account.mfa.totp.confirm' => 'account.mfa.totp.confirm',
        'account.mfa.totp.revoke.submit' => 'account.mfa.totp.revoke',
        'account.passkey.register.options' => 'account.passkey.register',
        'account.passkey.register.verify' => 'account.passkey.register',
        'account.passkey.revoke.submit' => 'account.passkey.revoke',
        'account.mfa.enable' => 'account.mfa.enable',
        'account.mfa.disable.submit' => 'account.mfa.disable',
        'account.mfa.recovery_codes.regenerate' => 'account.mfa.recovery_codes.regenerate',
        'account.person_profile.create.submit' => 'people.profile.create',
        'account.person_profile.update.submit' => 'people.profile.update',
        'account.person_profile.role.activate' => 'people.role.activate',
        'account.person_profile.role.deactivate.submit' => 'people.role.deactivate',
        'account.person_profile.memorizer_progress.submit' => 'people.memorizer_progress.update',
        'account.person_profile.dependent.create.submit' => 'people.dependent.create',
        'account.person_profile.dependent.update.submit' => 'people.dependent.update',
        'account.person_profile.dependent.memorizer_progress.submit' => 'people.memorizer_progress.update',
        'account.person_profile.guardianship.revoke.submit' => 'people.guardianship.revoke',
        'workspace.organizations.create.submit' => 'organizations.organization.create',
        'workspace.organizations.update.submit' => 'organizations.organization.update',
        'workspace.organizations.retire.submit' => 'organizations.organization.retire',
        'workspace.organizations.units.create.submit' => 'organizations.unit.create',
        'workspace.organizations.units.update.submit' => 'organizations.unit.update',
        'workspace.organizations.units.retire.submit' => 'organizations.unit.retire',
    ];

    /** @var list<string> */
    private const array IDEMPOTENT = [
        'account.registration.submit', 'account.password_recovery.reset.submit',
        'platform.security.accounts.suspend', 'platform.security.accounts.reactivate',
        'account.privileged_access.temporary.request.submit', 'account.privileged_access.support.request.submit',
        'account.privileged_access.approve.submit', 'workspace.privileged_access.approve.submit',
        'account.privileged_access.activate.submit', 'account.privileged_access.break_glass.activate.submit',
        'account.person_profile.create.submit', 'account.person_profile.update.submit',
        'account.person_profile.role.activate', 'account.person_profile.role.deactivate.submit',
        'account.person_profile.memorizer_progress.submit', 'account.person_profile.dependent.create.submit',
        'account.person_profile.dependent.update.submit', 'account.person_profile.dependent.memorizer_progress.submit',
        'account.person_profile.guardianship.revoke.submit',
        'workspace.organizations.create.submit', 'workspace.organizations.update.submit',
        'workspace.organizations.retire.submit', 'workspace.organizations.units.create.submit',
        'workspace.organizations.units.update.submit', 'workspace.organizations.units.retire.submit',
    ];

    /** @var list<string> */
    private const array JSON_MUTATIONS = [
        'account.mfa.login.passkey.options', 'account.mfa.login.passkey.verify', 'account.passkey.login.options',
        'account.passkey.login.verify', 'account.step_up.passkey.options', 'account.step_up.passkey.verify',
        'account.passkey.register.options', 'account.passkey.register.verify',
    ];

    /** @return array<string, RouteSecurityPolicy> */
    public function policies(): array
    {
        $policies = [];
        foreach (self::PUBLIC as $route) {
            $policies[$route] = $this->policy(RouteSecurityClassification::PUBLIC, $route);
        }
        foreach (self::AUTHENTICATED as $route) {
            $policies[$route] = $this->policy(RouteSecurityClassification::AUTHENTICATED, $route);
        }
        foreach (self::TENANT_REQUIRED as $route) {
            $policies[$route] = $this->policy(RouteSecurityClassification::TENANT_REQUIRED, $route);
        }
        foreach (self::BASE_ROLE_PERMISSIONS as $route => $_) {
            $policies[$route] = $this->policy(RouteSecurityClassification::BASE_ROLE_REQUIRED, $route);
        }

        if (count($policies) !== 126) {
            throw new LogicException('The closed production route-security catalog is incomplete.');
        }

        return $policies;
    }

    private function policy(RouteSecurityClassification $classification, string $route): RouteSecurityPolicy
    {
        $permission = self::BASE_ROLE_PERMISSIONS[$route] ?? null;
        $assurance = match ($permission) {
            'platform.accounts.view', 'platform.security_events.view',
            'workspace.organizations.manage', 'workspace.organization_units.manage' => 'MULTI_FACTOR',
            'workspace.organizations.view', 'workspace.organization_units.view' => 'PRIMARY',
            'platform.accounts.suspend', 'platform.accounts.reactivate', 'platform.audit.verify' => 'PHISHING_RESISTANT',
            default => null,
        };

        return new RouteSecurityPolicy(
            $classification,
            $classification === RouteSecurityClassification::TENANT_REQUIRED || str_starts_with($route, 'workspace.organizations.'),
            $permission,
            $assurance,
            self::STEP_UP_ACTIONS[$route] ?? null,
            self::CSRF_ACTIONS[$route] ?? null,
            in_array($route, self::IDEMPOTENT, true),
            $classification !== RouteSecurityClassification::PUBLIC
                || str_contains($route, 'verification')
                || str_contains($route, '.password_recovery.')
                || str_contains($route, '.mfa.')
                || str_contains($route, '.passkey.'),
            in_array($route, self::JSON_MUTATIONS, true) ? 'application/json' : null,
        );
    }
}
