<?php

declare(strict_types=1);

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
): RouteCollection {
    return new RouteCollection(
        new Route('system.home', [HttpMethod::GET], new RoutePattern('/'), $homeController),
        new Route('system.about.page', [HttpMethod::GET], new RoutePattern('/system/about'), $aboutPageController),
        new Route('system.status.page', [HttpMethod::GET], new RoutePattern('/system/status'), $statusPageController),
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
    );
};
