<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

use Qmdb\Shared\Http\Controller\LivenessController;
use Qmdb\Modules\IdentitySessions\Interface\Http\ApplicationReadinessController;
use Qmdb\Shared\Http\Controller\SystemAboutApiController;
use Qmdb\Shared\Http\Controller\SystemAboutPageController;
use Qmdb\Shared\Http\Controller\SystemHomeController;
use Qmdb\Shared\Http\Controller\SystemStatusPageController;
use Qmdb\Modules\IdentityAccess\Interface\Http\AccountRegistrationAcceptedController;
use Qmdb\Modules\IdentityAccess\Interface\Http\AccountRegistrationFormController;
use Qmdb\Modules\IdentityAccess\Interface\Http\AccountRegistrationSubmitController;
use Qmdb\Modules\IdentityAccess\Interface\Http\EmailVerificationCompletedController;
use Qmdb\Modules\IdentityAccess\Interface\Http\EmailVerificationFormController;
use Qmdb\Modules\IdentityAccess\Interface\Http\EmailVerificationResendFormController;
use Qmdb\Modules\IdentityAccess\Interface\Http\EmailVerificationResendSubmitController;
use Qmdb\Modules\IdentityAccess\Interface\Http\EmailVerificationSubmitController;
use Qmdb\Modules\IdentitySessions\Interface\Http\AccountSecurityController;
use Qmdb\Modules\IdentitySessions\Interface\Http\DeviceRevocationController;
use Qmdb\Modules\IdentitySessions\Interface\Http\LoginFormController;
use Qmdb\Modules\IdentitySessions\Interface\Http\LoginSubmitController;
use Qmdb\Modules\IdentitySessions\Interface\Http\LogoutController;
use Qmdb\Modules\IdentitySessions\Interface\Http\SessionRevocationController;
use Qmdb\Modules\IdentityRecovery\Interface\Http\PasswordRecoveryRequestAcceptedController;
use Qmdb\Modules\IdentityRecovery\Interface\Http\PasswordRecoveryRequestFormController;
use Qmdb\Modules\IdentityRecovery\Interface\Http\PasswordRecoveryRequestSubmitController;
use Qmdb\Modules\IdentityRecovery\Interface\Http\PasswordResetCompletedController;
use Qmdb\Modules\IdentityRecovery\Interface\Http\PasswordResetFormController;
use Qmdb\Modules\IdentityRecovery\Interface\Http\PasswordResetSubmitController;
use Qmdb\Modules\IdentityMultiFactor\Interface\Http\IdentityMultiFactorController;
use Qmdb\Modules\TenancyContext\Interface\Http\AccountWorkspacesController;
use Qmdb\Modules\TenancyContext\Interface\Http\CurrentWorkspaceController;
use Qmdb\Modules\TenancyContext\Interface\Http\WorkspaceClearController;
use Qmdb\Modules\TenancyContext\Interface\Http\WorkspaceSwitchController;
use Qmdb\Modules\SecurityPrivilegedAccess\Interface\Http\PrivilegedAccessController;
use Qmdb\Modules\IdentityAccountState\Interface\Http\AccountStateSecurityController;
use Qmdb\Modules\IdentityAccountState\Interface\Http\SecurityAuditViewerController;
use Qmdb\Modules\Geography\Interface\Http\GeographyChildrenLookupController;
use Qmdb\Modules\Geography\Interface\Http\NigeriaGeographyAreaController;
use Qmdb\Modules\Geography\Interface\Http\NigeriaGeographyDirectoryController;
use Qmdb\Modules\People\Interface\Http\PeopleProfilesController;
use Qmdb\Modules\Organizations\Interface\Http\OrganizationsRegistryController;
use Qmdb\Modules\OrganizationAffiliations\Interface\Http\OrganizationAffiliationsController;
use Qmdb\Shared\Http\Routing\HttpMethod;
use Qmdb\Shared\Http\Routing\Route;
use Qmdb\Shared\Http\Routing\RouteCollection;
use Qmdb\Shared\Http\Routing\RoutePattern;

return static function (
    SystemHomeController $homeController,
    SystemAboutPageController $aboutPageController,
    SystemStatusPageController $statusPageController,
    SystemAboutApiController $aboutApiController,
    LivenessController $livenessController,
    ApplicationReadinessController $readinessController,
    AccountRegistrationFormController $registrationForm,
    AccountRegistrationSubmitController $registrationSubmit,
    AccountRegistrationAcceptedController $registrationAccepted,
    EmailVerificationResendFormController $resendForm,
    EmailVerificationResendSubmitController $resendSubmit,
    EmailVerificationFormController $verificationForm,
    EmailVerificationSubmitController $verificationSubmit,
    EmailVerificationCompletedController $verificationCompleted,
    LoginFormController $loginForm,
    LoginSubmitController $loginSubmit,
    LogoutController $logout,
    AccountSecurityController $accountSecurity,
    SessionRevocationController $sessionRevocation,
    DeviceRevocationController $deviceRevocation,
    PasswordRecoveryRequestFormController $recoveryRequestForm,
    PasswordRecoveryRequestSubmitController $recoveryRequestSubmit,
    PasswordRecoveryRequestAcceptedController $recoveryRequestAccepted,
    PasswordResetFormController $passwordResetForm,
    PasswordResetSubmitController $passwordResetSubmit,
    PasswordResetCompletedController $passwordResetCompleted,
    IdentityMultiFactorController $multiFactor,
    AccountWorkspacesController $accountWorkspaces,
    WorkspaceSwitchController $workspaceSwitch,
    WorkspaceClearController $workspaceClear,
    CurrentWorkspaceController $currentWorkspace,
    PrivilegedAccessController $privilegedAccess,
    AccountStateSecurityController $accountState,
    SecurityAuditViewerController $securityAudit,
    NigeriaGeographyDirectoryController $geographyDirectory,
    NigeriaGeographyAreaController $geographyArea,
    GeographyChildrenLookupController $geographyChildren,
    PeopleProfilesController $peopleProfiles,
    OrganizationsRegistryController $organizationsRegistry,
    OrganizationAffiliationsController $organizationAffiliations,
): RouteCollection {
    return new RouteCollection(
        new Route('system.home', [HttpMethod::GET], new RoutePattern('/'), $homeController),
        new Route('system.about.page', [HttpMethod::GET], new RoutePattern('/system/about'), $aboutPageController),
        new Route('system.status.page', [HttpMethod::GET], new RoutePattern('/system/status'), $statusPageController),
        new Route(
            'geography.nigeria.index',
            [HttpMethod::GET],
            new RoutePattern('/locations/nigeria'),
            $geographyDirectory,
        ),
        new Route(
            'geography.nigeria.area',
            [HttpMethod::GET],
            new RoutePattern('/locations/nigeria/{levelOneSlug}'),
            $geographyArea,
        ),
        new Route(
            'geography.lookup.children',
            [HttpMethod::GET],
            new RoutePattern('/lookups/geography/children'),
            $geographyChildren,
        ),
        new Route(
            'system.health.live',
            [HttpMethod::GET],
            new RoutePattern('/health/live'),
            $livenessController,
        ),
        new Route(
            'system.health.ready',
            [HttpMethod::GET],
            new RoutePattern('/health/ready'),
            $readinessController,
        ),
        new Route(
            'api.v1.system.about',
            [HttpMethod::GET],
            new RoutePattern('/api/v1/system/about'),
            $aboutApiController,
        ),
        new Route('account.registration.form', [HttpMethod::GET], new RoutePattern('/register'), $registrationForm),
        new Route(
            'account.registration.submit',
            [HttpMethod::POST],
            new RoutePattern('/register'),
            $registrationSubmit,
        ),
        new Route(
            'account.registration.accepted',
            [HttpMethod::GET],
            new RoutePattern('/register/accepted'),
            $registrationAccepted,
        ),
        new Route(
            'account.email_verification.resend.form',
            [HttpMethod::GET],
            new RoutePattern('/verify-email/resend'),
            $resendForm,
        ),
        new Route(
            'account.email_verification.resend.submit',
            [HttpMethod::POST],
            new RoutePattern('/verify-email/resend'),
            $resendSubmit,
        ),
        new Route(
            'account.email_verification.completed',
            [HttpMethod::GET],
            new RoutePattern('/verify-email/completed'),
            $verificationCompleted,
        ),
        new Route(
            'account.email_verification.form',
            [HttpMethod::GET],
            new RoutePattern('/verify-email/{challengeId}'),
            $verificationForm,
        ),
        new Route(
            'account.email_verification.submit',
            [HttpMethod::POST],
            new RoutePattern('/verify-email/{challengeId}'),
            $verificationSubmit,
        ),
        new Route('account.login.form', [HttpMethod::GET], new RoutePattern('/login'), $loginForm),
        new Route('account.login.submit', [HttpMethod::POST], new RoutePattern('/login'), $loginSubmit),
        new Route('account.logout', [HttpMethod::POST], new RoutePattern('/logout'), $logout),
        new Route(
            'account.workspaces.index',
            [HttpMethod::GET],
            new RoutePattern('/account/workspaces'),
            $accountWorkspaces,
        ),
        new Route(
            'account.workspaces.switch',
            [HttpMethod::POST],
            new RoutePattern('/account/workspaces/switch'),
            $workspaceSwitch,
        ),
        new Route(
            'account.workspaces.clear',
            [HttpMethod::POST],
            new RoutePattern('/account/workspaces/clear'),
            $workspaceClear,
        ),
        new Route('workspace.current', [HttpMethod::GET], new RoutePattern('/workspace'), $currentWorkspace),
        new Route('platform.security.accounts.detail', [HttpMethod::GET], new RoutePattern('/platform/security/accounts/{accountId}'), $accountState),
        new Route('platform.security.accounts.suspend', [HttpMethod::GET, HttpMethod::POST], new RoutePattern('/platform/security/accounts/{accountId}/suspend'), $accountState),
        new Route('platform.security.accounts.reactivate', [HttpMethod::GET, HttpMethod::POST], new RoutePattern('/platform/security/accounts/{accountId}/reactivate'), $accountState),
        new Route('account.security.events', [HttpMethod::GET], new RoutePattern('/account/security/events'), $securityAudit),
        new Route('platform.security.events', [HttpMethod::GET], new RoutePattern('/platform/security/events'), $securityAudit),
        new Route('platform.security.events.detail', [HttpMethod::GET], new RoutePattern('/platform/security/events/{eventId}'), $securityAudit),
        new Route('platform.security.audit', [HttpMethod::GET], new RoutePattern('/platform/security/audit'), $securityAudit),
        new Route('account.privileged_access.index', [HttpMethod::GET], new RoutePattern('/account/security/privileged-access'), $privilegedAccess),
        new Route('account.privileged_access.temporary.request.form', [HttpMethod::GET], new RoutePattern('/account/security/privileged-access/temporary/request'), $privilegedAccess),
        new Route('account.privileged_access.temporary.request.submit', [HttpMethod::POST], new RoutePattern('/account/security/privileged-access/temporary/request'), $privilegedAccess),
        new Route('account.privileged_access.support.request.form', [HttpMethod::GET], new RoutePattern('/account/security/support-access/request'), $privilegedAccess),
        new Route('account.privileged_access.support.request.submit', [HttpMethod::POST], new RoutePattern('/account/security/support-access/request'), $privilegedAccess),
        new Route('account.privileged_access.detail', [HttpMethod::GET], new RoutePattern('/account/security/privileged-access/{requestId}'), $privilegedAccess),
        new Route('account.privileged_access.approve.form', [HttpMethod::GET], new RoutePattern('/account/security/privileged-access/{requestId}/approve'), $privilegedAccess),
        new Route('account.privileged_access.approve.submit', [HttpMethod::POST], new RoutePattern('/account/security/privileged-access/{requestId}/approve'), $privilegedAccess),
        new Route('account.privileged_access.reject.submit', [HttpMethod::POST], new RoutePattern('/account/security/privileged-access/{requestId}/reject'), $privilegedAccess),
        new Route('account.privileged_access.cancel.submit', [HttpMethod::POST], new RoutePattern('/account/security/privileged-access/{requestId}/cancel'), $privilegedAccess),
        new Route('account.privileged_access.activate.submit', [HttpMethod::POST], new RoutePattern('/account/security/privileged-access/{requestId}/activate'), $privilegedAccess),
        new Route('account.privileged_access.revoke.submit', [HttpMethod::POST], new RoutePattern('/account/security/privileged-access/{requestId}/revoke'), $privilegedAccess),
        new Route('workspace.privileged_access.approve.form', [HttpMethod::GET], new RoutePattern('/workspace/security/support-access/{requestId}/approve'), $privilegedAccess),
        new Route('workspace.privileged_access.approve.submit', [HttpMethod::POST], new RoutePattern('/workspace/security/support-access/{requestId}/approve'), $privilegedAccess),
        new Route('account.privileged_access.active.end.submit', [HttpMethod::POST], new RoutePattern('/account/security/privileged-access/active/end'), $privilegedAccess),
        new Route('account.privileged_access.break_glass.activate.form', [HttpMethod::GET], new RoutePattern('/account/security/break-glass/activate'), $privilegedAccess),
        new Route('account.privileged_access.break_glass.activate.submit', [HttpMethod::POST], new RoutePattern('/account/security/break-glass/activate'), $privilegedAccess),
        new Route('account.privileged_access.review.form', [HttpMethod::GET], new RoutePattern('/account/security/privileged-access/{requestId}/review'), $privilegedAccess),
        new Route('account.privileged_access.review.submit', [HttpMethod::POST], new RoutePattern('/account/security/privileged-access/{requestId}/review'), $privilegedAccess),
        new Route(
            'account.security.sessions',
            [HttpMethod::GET],
            new RoutePattern('/account/security/sessions'),
            $accountSecurity,
        ),
        new Route(
            'account.security.session_revoke.form',
            [HttpMethod::GET],
            new RoutePattern('/account/security/sessions/{sessionId}/revoke'),
            $sessionRevocation,
        ),
        new Route(
            'account.security.session_revoke.submit',
            [HttpMethod::POST],
            new RoutePattern('/account/security/sessions/{sessionId}/revoke'),
            $sessionRevocation,
        ),
        new Route(
            'account.security.device_revoke.form',
            [HttpMethod::GET],
            new RoutePattern('/account/security/devices/{deviceId}/revoke'),
            $deviceRevocation,
        ),
        new Route(
            'account.security.device_revoke.submit',
            [HttpMethod::POST],
            new RoutePattern('/account/security/devices/{deviceId}/revoke'),
            $deviceRevocation,
        ),
        new Route(
            'account.password_recovery.request.form',
            [HttpMethod::GET],
            new RoutePattern('/forgot-password'),
            $recoveryRequestForm,
        ),
        new Route(
            'account.password_recovery.request.submit',
            [HttpMethod::POST],
            new RoutePattern('/forgot-password'),
            $recoveryRequestSubmit,
        ),
        new Route(
            'account.password_recovery.request.accepted',
            [HttpMethod::GET],
            new RoutePattern('/forgot-password/accepted'),
            $recoveryRequestAccepted,
        ),
        new Route(
            'account.password_recovery.reset.form',
            [HttpMethod::GET],
            new RoutePattern('/reset-password/{challengeId}'),
            $passwordResetForm,
        ),
        new Route(
            'account.password_recovery.reset.submit',
            [HttpMethod::POST],
            new RoutePattern('/reset-password/{challengeId}'),
            $passwordResetSubmit,
        ),
        new Route(
            'account.password_recovery.reset.completed',
            [HttpMethod::GET],
            new RoutePattern('/reset-password/completed'),
            $passwordResetCompleted,
        ),
        new Route('account.mfa.login.form', [HttpMethod::GET], new RoutePattern('/login/mfa'), $multiFactor),
        new Route(
            'account.mfa.login.totp',
            [HttpMethod::POST],
            new RoutePattern('/login/mfa/totp'),
            $multiFactor,
        ),
        new Route(
            'account.mfa.login.recovery_code',
            [HttpMethod::POST],
            new RoutePattern('/login/mfa/recovery-code'),
            $multiFactor,
        ),
        new Route(
            'account.mfa.login.passkey.options',
            [HttpMethod::POST],
            new RoutePattern('/login/mfa/passkey/options'),
            $multiFactor,
        ),
        new Route(
            'account.mfa.login.passkey.verify',
            [HttpMethod::POST],
            new RoutePattern('/login/mfa/passkey/verify'),
            $multiFactor,
        ),
        new Route(
            'account.passkey.login.options',
            [HttpMethod::POST],
            new RoutePattern('/login/passkey/options'),
            $multiFactor,
        ),
        new Route(
            'account.passkey.login.verify',
            [HttpMethod::POST],
            new RoutePattern('/login/passkey/verify'),
            $multiFactor,
        ),
        new Route(
            'account.step_up.form',
            [HttpMethod::GET],
            new RoutePattern('/account/step-up/{action}'),
            $multiFactor,
        ),
        new Route(
            'account.step_up.password',
            [HttpMethod::POST],
            new RoutePattern('/account/step-up/password'),
            $multiFactor,
        ),
        new Route(
            'account.step_up.totp',
            [HttpMethod::POST],
            new RoutePattern('/account/step-up/totp'),
            $multiFactor,
        ),
        new Route(
            'account.step_up.recovery_code',
            [HttpMethod::POST],
            new RoutePattern('/account/step-up/recovery-code'),
            $multiFactor,
        ),
        new Route(
            'account.step_up.passkey.options',
            [HttpMethod::POST],
            new RoutePattern('/account/step-up/passkey/options'),
            $multiFactor,
        ),
        new Route(
            'account.step_up.passkey.verify',
            [HttpMethod::POST],
            new RoutePattern('/account/step-up/passkey/verify'),
            $multiFactor,
        ),
        new Route(
            'account.security.authentication',
            [HttpMethod::GET],
            new RoutePattern('/account/security/authentication'),
            $multiFactor,
        ),
        new Route(
            'account.mfa.totp.enroll.form',
            [HttpMethod::GET],
            new RoutePattern('/account/security/mfa/totp/enroll'),
            $multiFactor,
        ),
        new Route(
            'account.mfa.totp.enroll.submit',
            [HttpMethod::POST],
            new RoutePattern('/account/security/mfa/totp/enroll'),
            $multiFactor,
        ),
        new Route(
            'account.mfa.totp.confirm',
            [HttpMethod::POST],
            new RoutePattern('/account/security/mfa/totp/{authenticatorId}/confirm'),
            $multiFactor,
        ),
        new Route(
            'account.mfa.totp.qr',
            [HttpMethod::GET],
            new RoutePattern('/account/security/mfa/totp/{authenticatorId}/qr'),
            $multiFactor,
        ),
        new Route(
            'account.mfa.totp.revoke.form',
            [HttpMethod::GET],
            new RoutePattern('/account/security/mfa/totp/{authenticatorId}/revoke'),
            $multiFactor,
        ),
        new Route(
            'account.mfa.totp.revoke.submit',
            [HttpMethod::POST],
            new RoutePattern('/account/security/mfa/totp/{authenticatorId}/revoke'),
            $multiFactor,
        ),
        new Route(
            'account.passkey.register.form',
            [HttpMethod::GET],
            new RoutePattern('/account/security/passkeys/register'),
            $multiFactor,
        ),
        new Route(
            'account.passkey.register.options',
            [HttpMethod::POST],
            new RoutePattern('/account/security/passkeys/registration/options'),
            $multiFactor,
        ),
        new Route(
            'account.passkey.register.verify',
            [HttpMethod::POST],
            new RoutePattern('/account/security/passkeys/registration/verify'),
            $multiFactor,
        ),
        new Route(
            'account.passkey.revoke.form',
            [HttpMethod::GET],
            new RoutePattern('/account/security/passkeys/{passkeyId}/revoke'),
            $multiFactor,
        ),
        new Route(
            'account.passkey.revoke.submit',
            [HttpMethod::POST],
            new RoutePattern('/account/security/passkeys/{passkeyId}/revoke'),
            $multiFactor,
        ),
        new Route(
            'account.mfa.enable',
            [HttpMethod::POST],
            new RoutePattern('/account/security/mfa/enable'),
            $multiFactor,
        ),
        new Route(
            'account.mfa.disable.form',
            [HttpMethod::GET],
            new RoutePattern('/account/security/mfa/disable'),
            $multiFactor,
        ),
        new Route(
            'account.mfa.disable.submit',
            [HttpMethod::POST],
            new RoutePattern('/account/security/mfa/disable'),
            $multiFactor,
        ),
        new Route(
            'account.mfa.recovery_codes.status',
            [HttpMethod::GET],
            new RoutePattern('/account/security/mfa/recovery-codes'),
            $multiFactor,
        ),
        new Route(
            'account.mfa.recovery_codes.regenerate',
            [HttpMethod::POST],
            new RoutePattern('/account/security/mfa/recovery-codes/regenerate'),
            $multiFactor,
        ),
        new Route('account.person_profile.view', [HttpMethod::GET], new RoutePattern('/account/profile'), $peopleProfiles),
        new Route('account.person_profile.create.form', [HttpMethod::GET], new RoutePattern('/account/profile/create'), $peopleProfiles),
        new Route('account.person_profile.create.submit', [HttpMethod::POST], new RoutePattern('/account/profile'), $peopleProfiles),
        new Route('account.person_profile.edit.form', [HttpMethod::GET], new RoutePattern('/account/profile/edit'), $peopleProfiles),
        new Route('account.person_profile.update.submit', [HttpMethod::POST], new RoutePattern('/account/profile/update'), $peopleProfiles),
        new Route('account.person_profile.role.activate', [HttpMethod::POST], new RoutePattern('/account/profile/roles/{role_type}/activate'), $peopleProfiles),
        new Route('account.person_profile.role.deactivate.form', [HttpMethod::GET], new RoutePattern('/account/profile/roles/{role_type}/deactivate'), $peopleProfiles),
        new Route('account.person_profile.role.deactivate.submit', [HttpMethod::POST], new RoutePattern('/account/profile/roles/{role_type}/deactivate'), $peopleProfiles),
        new Route('account.person_profile.memorizer_progress.form', [HttpMethod::GET], new RoutePattern('/account/profile/memorizer-progress'), $peopleProfiles),
        new Route('account.person_profile.memorizer_progress.submit', [HttpMethod::POST], new RoutePattern('/account/profile/memorizer-progress'), $peopleProfiles),
        new Route('account.person_profile.dependents.index', [HttpMethod::GET], new RoutePattern('/account/dependents'), $peopleProfiles),
        new Route('account.person_profile.dependent.create.form', [HttpMethod::GET], new RoutePattern('/account/dependents/create'), $peopleProfiles),
        new Route('account.person_profile.dependent.create.submit', [HttpMethod::POST], new RoutePattern('/account/dependents'), $peopleProfiles),
        new Route('account.person_profile.dependent.view', [HttpMethod::GET], new RoutePattern('/account/dependents/{person_id}'), $peopleProfiles),
        new Route('account.person_profile.dependent.edit.form', [HttpMethod::GET], new RoutePattern('/account/dependents/{person_id}/edit'), $peopleProfiles),
        new Route('account.person_profile.dependent.update.submit', [HttpMethod::POST], new RoutePattern('/account/dependents/{person_id}/update'), $peopleProfiles),
        new Route('account.person_profile.dependent.memorizer_progress.form', [HttpMethod::GET], new RoutePattern('/account/dependents/{person_id}/memorizer-progress'), $peopleProfiles),
        new Route('account.person_profile.dependent.memorizer_progress.submit', [HttpMethod::POST], new RoutePattern('/account/dependents/{person_id}/memorizer-progress'), $peopleProfiles),
        new Route('account.person_profile.guardianship.revoke.form', [HttpMethod::GET], new RoutePattern('/account/dependents/{person_id}/guardianship/revoke'), $peopleProfiles),
        new Route('account.person_profile.guardianship.revoke.submit', [HttpMethod::POST], new RoutePattern('/account/dependents/{person_id}/guardianship/revoke'), $peopleProfiles),
        new Route('workspace.organizations.index', [HttpMethod::GET], new RoutePattern('/workspace/organizations'), $organizationsRegistry),
        new Route('workspace.organizations.create.form', [HttpMethod::GET], new RoutePattern('/workspace/organizations/create'), $organizationsRegistry),
        new Route('workspace.organizations.create.submit', [HttpMethod::POST], new RoutePattern('/workspace/organizations'), $organizationsRegistry),
        new Route('workspace.organizations.view', [HttpMethod::GET], new RoutePattern('/workspace/organizations/{organizationId}'), $organizationsRegistry),
        new Route('workspace.organizations.edit.form', [HttpMethod::GET], new RoutePattern('/workspace/organizations/{organizationId}/edit'), $organizationsRegistry),
        new Route('workspace.organizations.update.submit', [HttpMethod::POST], new RoutePattern('/workspace/organizations/{organizationId}/update'), $organizationsRegistry),
        new Route('workspace.organizations.retire.form', [HttpMethod::GET], new RoutePattern('/workspace/organizations/{organizationId}/retire'), $organizationsRegistry),
        new Route('workspace.organizations.retire.submit', [HttpMethod::POST], new RoutePattern('/workspace/organizations/{organizationId}/retire'), $organizationsRegistry),
        new Route('workspace.organizations.units.create.form', [HttpMethod::GET], new RoutePattern('/workspace/organizations/{organizationId}/units/create'), $organizationsRegistry),
        new Route('workspace.organizations.units.create.submit', [HttpMethod::POST], new RoutePattern('/workspace/organizations/{organizationId}/units'), $organizationsRegistry),
        new Route('workspace.organizations.units.view', [HttpMethod::GET], new RoutePattern('/workspace/organizations/{organizationId}/units/{unitId}'), $organizationsRegistry),
        new Route('workspace.organizations.units.edit.form', [HttpMethod::GET], new RoutePattern('/workspace/organizations/{organizationId}/units/{unitId}/edit'), $organizationsRegistry),
        new Route('workspace.organizations.units.update.submit', [HttpMethod::POST], new RoutePattern('/workspace/organizations/{organizationId}/units/{unitId}/update'), $organizationsRegistry),
        new Route('workspace.organizations.units.retire.form', [HttpMethod::GET], new RoutePattern('/workspace/organizations/{organizationId}/units/{unitId}/retire'), $organizationsRegistry),
        new Route('workspace.organizations.units.retire.submit', [HttpMethod::POST], new RoutePattern('/workspace/organizations/{organizationId}/units/{unitId}/retire'), $organizationsRegistry),
        new Route('workspace.organizations.affiliations.index', [HttpMethod::GET], new RoutePattern('/workspace/organizations/{organizationId}/affiliations'), $organizationAffiliations),
        new Route('workspace.organizations.affiliations.request.form', [HttpMethod::GET], new RoutePattern('/workspace/organizations/{organizationId}/affiliations/request'), $organizationAffiliations),
        new Route('workspace.organizations.affiliations.request.submit', [HttpMethod::POST], new RoutePattern('/workspace/organizations/{organizationId}/affiliations/request'), $organizationAffiliations),
        new Route('workspace.organizations.affiliations.view', [HttpMethod::GET], new RoutePattern('/workspace/organizations/{organizationId}/affiliations/{affiliationId}'), $organizationAffiliations),
        new Route('workspace.organizations.affiliations.assignments.form', [HttpMethod::GET], new RoutePattern('/workspace/organizations/{organizationId}/affiliations/{affiliationId}/assignments'), $organizationAffiliations),
        new Route('workspace.organizations.affiliations.assignments.submit', [HttpMethod::POST], new RoutePattern('/workspace/organizations/{organizationId}/affiliations/{affiliationId}/assignments'), $organizationAffiliations),
        new Route('workspace.organizations.affiliations.withdraw.form', [HttpMethod::GET], new RoutePattern('/workspace/organizations/{organizationId}/affiliations/{affiliationId}/withdraw'), $organizationAffiliations),
        new Route('workspace.organizations.affiliations.withdraw.submit', [HttpMethod::POST], new RoutePattern('/workspace/organizations/{organizationId}/affiliations/{affiliationId}/withdraw'), $organizationAffiliations),
        new Route('workspace.organizations.affiliations.suspend.form', [HttpMethod::GET], new RoutePattern('/workspace/organizations/{organizationId}/affiliations/{affiliationId}/suspend'), $organizationAffiliations),
        new Route('workspace.organizations.affiliations.suspend.submit', [HttpMethod::POST], new RoutePattern('/workspace/organizations/{organizationId}/affiliations/{affiliationId}/suspend'), $organizationAffiliations),
        new Route('workspace.organizations.affiliations.resume.form', [HttpMethod::GET], new RoutePattern('/workspace/organizations/{organizationId}/affiliations/{affiliationId}/resume'), $organizationAffiliations),
        new Route('workspace.organizations.affiliations.resume.submit', [HttpMethod::POST], new RoutePattern('/workspace/organizations/{organizationId}/affiliations/{affiliationId}/resume'), $organizationAffiliations),
        new Route('workspace.organizations.affiliations.end.form', [HttpMethod::GET], new RoutePattern('/workspace/organizations/{organizationId}/affiliations/{affiliationId}/end'), $organizationAffiliations),
        new Route('workspace.organizations.affiliations.end.submit', [HttpMethod::POST], new RoutePattern('/workspace/organizations/{organizationId}/affiliations/{affiliationId}/end'), $organizationAffiliations),
        new Route('account.affiliations.index', [HttpMethod::GET], new RoutePattern('/account/affiliations'), $organizationAffiliations),
        new Route('account.affiliations.detail', [HttpMethod::GET], new RoutePattern('/account/affiliations/{affiliationId}'), $organizationAffiliations),
        new Route('account.affiliations.accept.form', [HttpMethod::GET], new RoutePattern('/account/affiliations/{affiliationId}/accept'), $organizationAffiliations),
        new Route('account.affiliations.accept.submit', [HttpMethod::POST], new RoutePattern('/account/affiliations/{affiliationId}/accept'), $organizationAffiliations),
        new Route('account.affiliations.decline.form', [HttpMethod::GET], new RoutePattern('/account/affiliations/{affiliationId}/decline'), $organizationAffiliations),
        new Route('account.affiliations.decline.submit', [HttpMethod::POST], new RoutePattern('/account/affiliations/{affiliationId}/decline'), $organizationAffiliations),
        new Route('account.affiliations.leave.form', [HttpMethod::GET], new RoutePattern('/account/affiliations/{affiliationId}/leave'), $organizationAffiliations),
        new Route('account.affiliations.leave.submit', [HttpMethod::POST], new RoutePattern('/account/affiliations/{affiliationId}/leave'), $organizationAffiliations),
    );
};
