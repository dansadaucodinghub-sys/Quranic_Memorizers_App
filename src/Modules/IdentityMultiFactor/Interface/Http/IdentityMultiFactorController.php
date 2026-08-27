<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Interface\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentityAccess\Security\Password\SensitivePlaintextPassword;
use Qmdb\Modules\IdentityMultiFactor\Application\AccountMfaDisablementService;
use Qmdb\Modules\IdentityMultiFactor\Application\AccountMfaEnablementService;
use Qmdb\Modules\IdentityMultiFactor\Application\AuthenticationTransactionCookieFactory;
use Qmdb\Modules\IdentityMultiFactor\Application\MfaLoginService;
use Qmdb\Modules\IdentityMultiFactor\Application\PasskeyRevocationService;
use Qmdb\Modules\IdentityMultiFactor\Application\RecoveryCodeRegenerationService;
use Qmdb\Modules\IdentityMultiFactor\Application\StepUpAuthenticationResult;
use Qmdb\Modules\IdentityMultiFactor\Application\StepUpAuthenticationService;
use Qmdb\Modules\IdentityMultiFactor\Application\StepUpGuard;
use Qmdb\Modules\IdentityMultiFactor\Application\TotpAuthenticatorRevocationService;
use Qmdb\Modules\IdentityMultiFactor\Application\TotpEnrollmentService;
use Qmdb\Modules\IdentityMultiFactor\Application\WebAuthnService;
use Qmdb\Modules\IdentityMultiFactor\Configuration\IdentityMultiFactorConfiguration;
use Qmdb\Modules\IdentityMultiFactor\Domain\AccountMfaPreferredMethod;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationMethod;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationTransactionCookieParser;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationTransactionCookieValue;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\AccountMfaPolicyRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\AuthenticationTransactionRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\PasskeyCredentialRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\RecoveryCodeSetRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\TotpAuthenticatorRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\IdentityMultiFactor\Domain\WebAuthnCeremonyPurpose;
use Qmdb\Modules\IdentitySessions\Application\AuthenticationCookieResponseDecorator;
use Qmdb\Modules\IdentitySessions\Application\DeviceCookieFactory;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Message\JsonResponseFactory;
use Qmdb\Shared\Http\Routing\RouteAttributes;
use Qmdb\Shared\Presentation\View\ViewData;

final readonly class IdentityMultiFactorController implements Controller
{
    public function __construct(
        private MultiFactorRequestInput $input,
        private IdentityCsrf $csrf,
        private IdentityAccessView $view,
        private AuthenticatedRequestGuard $guard,
        private MfaLoginService $mfaLogin,
        private StepUpAuthenticationService $stepUp,
        private StepUpGuard $stepUpGuard,
        private TotpEnrollmentService $totpEnrollment,
        private AccountMfaEnablementService $enablement,
        private AccountMfaDisablementService $disablement,
        private RecoveryCodeRegenerationService $recoveryCodeRegeneration,
        private TotpAuthenticatorRevocationService $totpRevocation,
        private PasskeyRevocationService $passkeyRevocation,
        private WebAuthnService $webauthn,
        private AuthenticationTransactionRepository $authenticationTransactions,
        private AccountMfaPolicyRepository $policies,
        private TotpAuthenticatorRepository $totpAuthenticators,
        private RecoveryCodeSetRepository $recoveryCodes,
        private PasskeyCredentialRepository $passkeys,
        private AuthenticationTransactionCookieFactory $transactionCookies,
        private AuthenticationTransactionCookieParser $transactionCookieParser,
        private DeviceCookieFactory $deviceCookies,
        private AuthenticationCookieResponseDecorator $cookies,
        private JsonResponseFactory $json,
        private Psr17Factory $psr17,
        private IdentityMultiFactorConfiguration $configuration,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $route = $request->getAttribute(RouteAttributes::NAME);
        if (!is_string($route)) {
            return $this->problem('ROUTE_UNAVAILABLE', 404);
        }

        try {
            return match ($route) {
                'account.mfa.login.form' => $this->loginMfaForm($request),
                'account.mfa.login.totp' => $this->loginMfaSubmit($request, CsrfAction::ACCOUNT_MFA_LOGIN_TOTP),
                'account.mfa.login.recovery_code' =>
                    $this->loginMfaSubmit($request, CsrfAction::ACCOUNT_MFA_LOGIN_RECOVERY_CODE),
                'account.mfa.login.passkey.options' => $this->mfaPasskeyOptions($request),
                'account.mfa.login.passkey.verify' => $this->mfaPasskeyVerify($request),
                'account.passkey.login.options' => $this->passkeyLoginOptions($request),
                'account.passkey.login.verify' => $this->passkeyLoginVerify($request),
                'account.step_up.form' => $this->stepUpForm($request),
                'account.step_up.password' => $this->stepUpSubmit($request, CsrfAction::ACCOUNT_STEP_UP_PASSWORD),
                'account.step_up.totp' => $this->stepUpSubmit($request, CsrfAction::ACCOUNT_STEP_UP_TOTP),
                'account.step_up.recovery_code' =>
                    $this->stepUpSubmit($request, CsrfAction::ACCOUNT_STEP_UP_RECOVERY_CODE),
                'account.step_up.passkey.options' => $this->stepUpPasskeyOptions($request),
                'account.step_up.passkey.verify' => $this->stepUpPasskeyVerify($request),
                'account.security.authentication' => $this->securityPage($request),
                'account.mfa.totp.enroll.form' => $this->totpStartForm($request),
                'account.mfa.totp.enroll.submit' => $this->totpStart($request),
                'account.mfa.totp.confirm' => $this->totpConfirm($request),
                'account.mfa.totp.qr' => $this->totpQr($request),
                'account.mfa.totp.revoke.form' => $this->totpRevokeForm($request),
                'account.mfa.totp.revoke.submit' => $this->totpRevoke($request),
                'account.passkey.register.form' => $this->passkeyRegisterForm($request),
                'account.passkey.register.options' => $this->passkeyRegistrationOptions($request),
                'account.passkey.register.verify' => $this->passkeyRegistrationVerify($request),
                'account.passkey.revoke.form' => $this->passkeyRevokeForm($request),
                'account.passkey.revoke.submit' => $this->passkeyRevoke($request),
                'account.mfa.enable' => $this->enableMfa($request),
                'account.mfa.disable.form' => $this->disableMfaForm($request),
                'account.mfa.disable.submit' => $this->disableMfa($request),
                'account.mfa.recovery_codes.status' => $this->recoveryCodeStatus($request),
                'account.mfa.recovery_codes.regenerate' => $this->regenerateRecoveryCodes($request),
                default => $this->problem('ROUTE_UNAVAILABLE', 404),
            };
        } catch (\InvalidArgumentException | \DomainException $exception) {
            return $this->problem('AUTHENTICATION_REQUEST_REJECTED', 422);
        }
    }

    private function loginMfaForm(ServerRequestInterface $request, string $error = ''): ResponseInterface
    {
        $cookie = $this->transactionCookie($request);
        if ($cookie === null) {
            $response = $this->view->redirect('/login');

            return array_key_exists($this->transactionCookies->name(), $request->getCookieParams())
                ? $this->cookies->apply($response, [$this->transactionCookies->clear()])
                : $response;
        }
        if ($this->mfaLogin->allowedMethods($cookie) === []) {
            return $this->cookies->apply(
                $this->view->redirect('/login'),
                [$this->transactionCookies->clear()],
            );
        }
        $csrf = $this->csrf->issue($request, CsrfAction::ACCOUNT_MFA_LOGIN_TOTP);
        $data = new ViewData([
            'csrf_totp' => $csrf['token'],
            'csrf_recovery' => $this->csrf->issueForCookie(
                $csrf['cookie'],
                CsrfAction::ACCOUNT_MFA_LOGIN_RECOVERY_CODE,
            ),
            'csrf_passkey' => $this->csrf->issueForCookie(
                $csrf['cookie'],
                CsrfAction::ACCOUNT_MFA_LOGIN_PASSKEY,
            ),
            'methods' => array_map(
                static fn (AuthenticationMethod $method): string => $method->value,
                $this->mfaLogin->allowedMethods($cookie),
            ),
            'error' => $error,
        ]);

        return $this->securePage(
            $this->view->render(
                $request,
                'pages.login-mfa',
                'fragments.login-mfa-form',
                $data,
                'title.login_mfa',
                $error === '' ? 200 : 422,
                $csrf['cookie'],
            ),
        );
    }

    private function loginMfaSubmit(ServerRequestInterface $request, CsrfAction $action): ResponseInterface
    {
        $form = $this->input->form($request);
        $cookie = $this->transactionCookie($request);
        $csrf = $this->csrf->issue($request, $action);
        if (
            $cookie === null || !$this->csrf->validates(
                $request,
                $action,
                $csrf['cookie'],
                $this->input->string($form, 'csrf_token', 512),
            )
        ) {
            return $this->loginMfaForm($request, 'form.error.csrf');
        }
        $code = $this->input->string($form, 'code', 128);
        $rawDevice = $request->getCookieParams()[$this->deviceCookies->name()] ?? null;
        $result = $action === CsrfAction::ACCOUNT_MFA_LOGIN_TOTP
            ? $this->mfaLogin->totp($cookie, $code, is_string($rawDevice) ? $rawDevice : null)
            : $this->mfaLogin->recoveryCode($cookie, $code, is_string($rawDevice) ? $rawDevice : null);
        if (!$result->succeeded) {
            return $this->loginMfaForm($request, 'mfa.error.invalid');
        }

        return $this->cookies->apply(
            $this->view->redirect('/account/security/authentication'),
            $result->cookies,
            $this->csrf->rotate(CsrfAction::ACCOUNT_LOGOUT)['cookie'],
        );
    }

    private function mfaPasskeyOptions(ServerRequestInterface $request): ResponseInterface
    {
        [$body, $cookie] = $this->jsonTransaction($request, CsrfAction::ACCOUNT_MFA_LOGIN_PASSKEY);
        $transaction = $this->authenticationTransactions->findTransaction($cookie);
        if ($transaction === null || $transaction->accountInternalId === null) {
            return $this->problem('INVALID_TRANSACTION', 422);
        }

        return $this->json->create($this->webauthn->assertionOptions(
            WebAuthnCeremonyPurpose::MFA_LOGIN,
            $transaction->accountInternalId,
            null,
            $transaction->internalId,
        ));
    }

    private function mfaPasskeyVerify(ServerRequestInterface $request): ResponseInterface
    {
        [$body, $cookie] = $this->jsonTransaction($request, CsrfAction::ACCOUNT_MFA_LOGIN_PASSKEY);
        $transaction = $this->authenticationTransactions->findTransaction($cookie);
        if ($transaction === null || $transaction->accountInternalId === null) {
            return $this->problem('INVALID_TRANSACTION', 422);
        }
        $passkey = $this->webauthn->verifyAssertion(
            $this->input->string($body, 'ceremonyId', 36),
            WebAuthnCeremonyPurpose::MFA_LOGIN,
            $this->credentialJson($body),
            $transaction->accountInternalId,
            null,
            $transaction->internalId,
        );
        $rawDevice = $request->getCookieParams()[$this->deviceCookies->name()] ?? null;
        $result = $this->mfaLogin->passkey($cookie, $passkey, is_string($rawDevice) ? $rawDevice : null);
        if (!$result->succeeded) {
            return $this->problem('INVALID_AUTHENTICATOR', 422);
        }

        return $this->cookies->apply(
            $this->json->create(['status' => 'authenticated', 'navigate' => '/account/security/authentication']),
            $result->cookies,
        );
    }

    private function passkeyLoginOptions(ServerRequestInterface $request): ResponseInterface
    {
        $this->validatedJson($request, CsrfAction::ACCOUNT_LOGIN);
        if (!$this->configuration->passwordlessEnabled) {
            return $this->problem('PASSKEY_LOGIN_UNAVAILABLE', 404);
        }

        return $this->json->create($this->webauthn->assertionOptions(
            WebAuthnCeremonyPurpose::PASSKEY_LOGIN,
            null,
            null,
            null,
        ));
    }

    private function passkeyLoginVerify(ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->validatedJson($request, CsrfAction::ACCOUNT_LOGIN);
        $passkey = $this->webauthn->verifyAssertion(
            $this->input->string($body, 'ceremonyId', 36),
            WebAuthnCeremonyPurpose::PASSKEY_LOGIN,
            $this->credentialJson($body),
        );
        $rawDevice = $request->getCookieParams()[$this->deviceCookies->name()] ?? null;
        $result = $this->mfaLogin->passwordless($passkey, is_string($rawDevice) ? $rawDevice : null);

        return $this->cookies->apply(
            $this->json->create(['status' => 'authenticated', 'navigate' => '/account/security/authentication']),
            $result->cookies,
        );
    }

    private function stepUpForm(ServerRequestInterface $request, string $error = ''): ResponseInterface
    {
        $context = $this->guard->context($request);
        if ($context === null) {
            return $this->guard->rejection($request);
        }
        $parameters = $this->parameters($request);
        $action = StepUpAction::tryFrom($parameters['action'] ?? '');
        if ($action === null) {
            return $this->problem('INVALID_STEP_UP_ACTION', 404);
        }
        $started = $this->stepUp->start($context, $action);
        if (!$started->succeeded || $started->cookie === null) {
            return $this->problem('STEP_UP_UNAVAILABLE', 409);
        }
        $csrf = $this->csrf->issue($request, CsrfAction::ACCOUNT_STEP_UP_PASSWORD);
        $data = new ViewData([
            'action' => $action->value,
            'methods' => array_map(
                static fn (AuthenticationMethod $method): string => $method->value,
                $this->stepUp->eligibleMethods($context, $action),
            ),
            'csrf_password' => $csrf['token'],
            'csrf_totp' => $this->csrf->issueForCookie($csrf['cookie'], CsrfAction::ACCOUNT_STEP_UP_TOTP),
            'csrf_recovery' => $this->csrf->issueForCookie(
                $csrf['cookie'],
                CsrfAction::ACCOUNT_STEP_UP_RECOVERY_CODE,
            ),
            'csrf_passkey' => $this->csrf->issueForCookie(
                $csrf['cookie'],
                CsrfAction::ACCOUNT_STEP_UP_PASSKEY,
            ),
            'error' => $error,
        ]);
        $response = $this->view->render(
            $request,
            'pages.account-step-up',
            'fragments.account-step-up-form',
            $data,
            'title.account_step_up',
            $error === '' ? 200 : 422,
            $csrf['cookie'],
        );

        return $this->cookies->apply($this->securePage($response), [$started->cookie]);
    }

    private function stepUpSubmit(ServerRequestInterface $request, CsrfAction $csrfAction): ResponseInterface
    {
        $context = $this->guard->context($request);
        if ($context === null) {
            return $this->guard->rejection($request);
        }
        $form = $this->input->form($request);
        $cookie = $this->transactionCookie($request);
        $csrf = $this->csrf->issue($request, $csrfAction);
        if (
            $cookie === null || !$this->csrf->validates(
                $request,
                $csrfAction,
                $csrf['cookie'],
                $this->input->string($form, 'csrf_token', 512),
            )
        ) {
            return $this->problem('CSRF_REJECTED', 403);
        }
        $result = match ($csrfAction) {
            CsrfAction::ACCOUNT_STEP_UP_PASSWORD => $this->stepUp->password(
                $context,
                $cookie,
                new SensitivePlaintextPassword($this->input->string($form, 'password', 1024)),
            ),
            CsrfAction::ACCOUNT_STEP_UP_TOTP =>
                $this->stepUp->totp($context, $cookie, $this->input->string($form, 'code', 16)),
            default => $this->stepUp->recoveryCode(
                $context,
                $cookie,
                $this->input->string($form, 'code', 128),
            ),
        };

        return $this->stepUpCompletion($request, $result);
    }

    private function stepUpPasskeyOptions(ServerRequestInterface $request): ResponseInterface
    {
        $context = $this->guard->context($request);
        if ($context === null) {
            return $this->guard->rejection($request);
        }
        [$body, $cookie] = $this->jsonTransaction($request, CsrfAction::ACCOUNT_STEP_UP_PASSKEY);
        $transaction = $this->authenticationTransactions->findTransaction($cookie);
        if (
            $transaction === null || $transaction->targetAction === null
            || $transaction->sessionInternalId !== $context->sessionInternalId
        ) {
            return $this->problem('INVALID_TRANSACTION', 422);
        }

        return $this->json->create($this->webauthn->assertionOptions(
            WebAuthnCeremonyPurpose::STEP_UP,
            $context->accountInternalId,
            $context->sessionInternalId,
            $transaction->internalId,
        ));
    }

    private function stepUpPasskeyVerify(ServerRequestInterface $request): ResponseInterface
    {
        $context = $this->guard->context($request);
        if ($context === null) {
            return $this->guard->rejection($request);
        }
        [$body, $cookie] = $this->jsonTransaction($request, CsrfAction::ACCOUNT_STEP_UP_PASSKEY);
        $transaction = $this->authenticationTransactions->findTransaction($cookie);
        if ($transaction === null || $transaction->targetAction === null) {
            return $this->problem('INVALID_TRANSACTION', 422);
        }
        $this->webauthn->verifyAssertion(
            $this->input->string($body, 'ceremonyId', 36),
            WebAuthnCeremonyPurpose::STEP_UP,
            $this->credentialJson($body),
            $context->accountInternalId,
            $context->sessionInternalId,
            $transaction->internalId,
        );
        $result = $this->stepUp->passkey($context, $cookie);
        if (!$result->succeeded || $result->action === null) {
            return $this->problem('STEP_UP_REJECTED', 422);
        }

        return $this->json->create([
            'status' => 'verified',
            'navigate' => $result->action->continuation(),
        ]);
    }

    private function securityPage(ServerRequestInterface $request): ResponseInterface
    {
        $context = $this->guard->context($request);
        if ($context === null) {
            return $this->guard->rejection($request);
        }
        $csrf = $this->csrf->issue($request, CsrfAction::ACCOUNT_MFA_ENABLE);
        $policy = $this->policies->findPolicy($context->accountInternalId);
        $totp = $this->totpAuthenticators->findActiveTotp($context->accountInternalId);
        $data = new ViewData([
            'policy_status' => $policy->status->value,
            'preferred_method' => $policy->preferredMethod->value ?? '',
            'methods' => array_map(
                static fn ($method): string => $method->value,
                $this->policies->activeMethods($context->accountInternalId),
            ),
            'passkeys' => array_map(static fn ($passkey): array => [
                'id' => $passkey->publicId,
                'name' => $passkey->displayName,
                'status' => $passkey->status->value,
                'version' => $passkey->version,
            ], $this->passkeys->listPasskeys($context->accountInternalId, true)),
            'totp_authenticator' => $totp === null ? [] : [
                'id' => $totp->publicId,
                'status' => $totp->status->value,
                'version' => $totp->version,
            ],
            'can_enable_mfa' => $this->stepUpGuard->permits($context, StepUpAction::MFA_ENABLE),
            'can_revoke_totp' => $this->stepUpGuard->permits($context, StepUpAction::MFA_REVOKE_TOTP),
            'can_revoke_passkey' => $this->stepUpGuard->permits($context, StepUpAction::MFA_REVOKE_PASSKEY),
            'csrf_enable' => $csrf['token'],
        ]);

        return $this->securePage($this->view->render(
            $request,
            'pages.account-authentication-security',
            'fragments.account-authentication-security-panel',
            $data,
            'title.account_authentication_security',
            cookie: $csrf['cookie'],
        ));
    }

    private function totpStartForm(ServerRequestInterface $request): ResponseInterface
    {
        $context = $this->guard->context($request);
        if ($context === null) {
            return $this->guard->rejection($request);
        }
        $csrf = $this->csrf->issue($request, CsrfAction::ACCOUNT_MFA_TOTP_ENROLL);

        return $this->securePage($this->view->render(
            $request,
            'pages.totp-enrollment-start',
            'fragments.totp-enrollment-confirm-form',
            new ViewData(['csrf_token' => $csrf['token'], 'mode' => 'start', 'authenticator_id' => '',
                'manual_secret' => '', 'error' => '']),
            'title.totp_enrollment',
            cookie: $csrf['cookie'],
        ));
    }

    private function totpStart(ServerRequestInterface $request): ResponseInterface
    {
        $context = $this->guard->context($request);
        if ($context === null) {
            return $this->guard->rejection($request);
        }
        $this->validatedForm($request, CsrfAction::ACCOUNT_MFA_TOTP_ENROLL);
        $result = $this->totpEnrollment->start($context);
        $csrf = $this->csrf->issue($request, CsrfAction::ACCOUNT_MFA_TOTP_CONFIRM);

        return $this->securePage($this->view->render(
            $request,
            'pages.totp-enrollment-confirm',
            'fragments.totp-enrollment-confirm-form',
            new ViewData(['csrf_token' => $csrf['token'], 'mode' => 'confirm',
                'authenticator_id' => $result->authenticatorPublicId,
                'manual_secret' => $result->manualSecret(), 'error' => '']),
            'title.totp_enrollment',
            cookie: $csrf['cookie'],
        ));
    }

    private function totpConfirm(ServerRequestInterface $request): ResponseInterface
    {
        $context = $this->guard->context($request);
        if ($context === null) {
            return $this->guard->rejection($request);
        }
        $form = $this->validatedForm($request, CsrfAction::ACCOUNT_MFA_TOTP_CONFIRM);
        $id = $this->parameters($request)['authenticatorId'] ?? '';
        if (!$this->totpEnrollment->confirm($context, $id, $this->input->string($form, 'code', 16))) {
            return $this->problem('INVALID_TOTP', 422);
        }

        return $this->view->redirect('/account/security/authentication');
    }

    private function totpQr(ServerRequestInterface $request): ResponseInterface
    {
        $context = $this->guard->context($request);
        if ($context === null) {
            return $this->guard->rejection($request);
        }
        $svg = $this->totpEnrollment->qrSvg(
            $context,
            $this->parameters($request)['authenticatorId'] ?? '',
        );
        $response = $this->psr17->createResponse(200)
            ->withHeader('Content-Type', 'image/svg+xml; charset=utf-8')
            ->withHeader('Cache-Control', 'private, no-store')
            ->withHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'");
        $response->getBody()->write($svg);

        return $response;
    }

    private function totpRevokeForm(ServerRequestInterface $request): ResponseInterface
    {
        $context = $this->guard->context($request);
        if ($context === null) {
            return $this->guard->rejection($request);
        }
        $id = $this->parameters($request)['authenticatorId'] ?? '';
        $authenticator = $this->totpAuthenticators->findTotp($context->accountInternalId, $id);
        if ($authenticator === null) {
            return $this->problem('AUTHENTICATOR_NOT_FOUND', 404);
        }
        $csrf = $this->csrf->issue($request, CsrfAction::ACCOUNT_MFA_TOTP_REVOKE);

        return $this->securePage($this->view->render(
            $request,
            'pages.totp-revoke-confirm',
            'fragments.totp-revoke-dialog',
            new ViewData(['csrf_token' => $csrf['token'], 'authenticator_id' => $id,
                'expected_version' => $authenticator->version]),
            'title.totp_revoke',
            cookie: $csrf['cookie'],
        ));
    }

    private function totpRevoke(ServerRequestInterface $request): ResponseInterface
    {
        $context = $this->guard->context($request);
        if ($context === null) {
            return $this->guard->rejection($request);
        }
        $form = $this->validatedForm($request, CsrfAction::ACCOUNT_MFA_TOTP_REVOKE);
        $this->totpRevocation->revoke(
            $context,
            $this->parameters($request)['authenticatorId'] ?? '',
            $this->input->integer($form, 'expected_version'),
        );

        return $this->view->redirect('/account/security/authentication');
    }

    private function passkeyRegisterForm(ServerRequestInterface $request): ResponseInterface
    {
        $context = $this->guard->context($request);
        if ($context === null) {
            return $this->guard->rejection($request);
        }
        $csrf = $this->csrf->issue($request, CsrfAction::ACCOUNT_PASSKEY_REGISTER);

        return $this->securePage($this->view->render(
            $request,
            'pages.passkey-registration',
            'fragments.passkey-list',
            new ViewData(['csrf_token' => $csrf['token'], 'passkeys' => []]),
            'title.passkey_registration',
            cookie: $csrf['cookie'],
        ));
    }

    private function passkeyRegistrationOptions(ServerRequestInterface $request): ResponseInterface
    {
        $context = $this->guard->context($request);
        if ($context === null) {
            return $this->guard->rejection($request);
        }
        $this->validatedJson($request, CsrfAction::ACCOUNT_PASSKEY_REGISTER);

        return $this->json->create($this->webauthn->registrationOptions($context));
    }

    private function passkeyRegistrationVerify(ServerRequestInterface $request): ResponseInterface
    {
        $context = $this->guard->context($request);
        if ($context === null) {
            return $this->guard->rejection($request);
        }
        $body = $this->validatedJson($request, CsrfAction::ACCOUNT_PASSKEY_REGISTER);
        $passkey = $this->webauthn->verifyRegistration(
            $context,
            $this->input->string($body, 'ceremonyId', 36),
            $this->input->string($body, 'displayName', 120),
            $this->credentialJson($body),
        );

        return $this->json->create([
            'status' => 'registered',
            'passkeyId' => $passkey->publicId,
            'navigate' => '/account/security/authentication',
        ], 201);
    }

    private function passkeyRevokeForm(ServerRequestInterface $request): ResponseInterface
    {
        $context = $this->guard->context($request);
        if ($context === null) {
            return $this->guard->rejection($request);
        }
        $id = $this->parameters($request)['passkeyId'] ?? '';
        $passkey = $this->passkeys->findPasskey($context->accountInternalId, $id);
        if ($passkey === null) {
            return $this->problem('PASSKEY_NOT_FOUND', 404);
        }
        $csrf = $this->csrf->issue($request, CsrfAction::ACCOUNT_PASSKEY_REVOKE);

        return $this->securePage($this->view->render(
            $request,
            'pages.passkey-revoke-confirm',
            'fragments.passkey-revoke-dialog',
            new ViewData(['csrf_token' => $csrf['token'], 'passkey_id' => $id,
                'passkey_name' => $passkey->displayName, 'expected_version' => $passkey->version]),
            'title.passkey_revoke',
            cookie: $csrf['cookie'],
        ));
    }

    private function passkeyRevoke(ServerRequestInterface $request): ResponseInterface
    {
        $context = $this->guard->context($request);
        if ($context === null) {
            return $this->guard->rejection($request);
        }
        $form = $this->validatedForm($request, CsrfAction::ACCOUNT_PASSKEY_REVOKE);
        $this->passkeyRevocation->revoke(
            $context,
            $this->parameters($request)['passkeyId'] ?? '',
            $this->input->integer($form, 'expected_version'),
        );

        return $this->view->redirect('/account/security/authentication');
    }

    private function enableMfa(ServerRequestInterface $request): ResponseInterface
    {
        $context = $this->guard->context($request);
        if ($context === null) {
            return $this->guard->rejection($request);
        }
        $form = $this->validatedForm($request, CsrfAction::ACCOUNT_MFA_ENABLE);
        $preferred = AccountMfaPreferredMethod::tryFrom($this->input->string($form, 'preferred_method', 16));
        if ($preferred === null) {
            return $this->problem('INVALID_PREFERRED_METHOD', 422);
        }
        $codes = $this->enablement->enable($context, $preferred);

        return $this->recoveryCodesOneTime($request, $codes->codes);
    }

    private function disableMfaForm(ServerRequestInterface $request): ResponseInterface
    {
        $context = $this->guard->context($request);
        if ($context === null) {
            return $this->guard->rejection($request);
        }
        $csrf = $this->csrf->issue($request, CsrfAction::ACCOUNT_MFA_DISABLE);

        return $this->securePage($this->view->render(
            $request,
            'pages.mfa-disable-confirm',
            'fragments.mfa-disable-dialog',
            new ViewData(['csrf_token' => $csrf['token']]),
            'title.mfa_disable',
            cookie: $csrf['cookie'],
        ));
    }

    private function disableMfa(ServerRequestInterface $request): ResponseInterface
    {
        $context = $this->guard->context($request);
        if ($context === null) {
            return $this->guard->rejection($request);
        }
        $this->validatedForm($request, CsrfAction::ACCOUNT_MFA_DISABLE);
        $this->disablement->disable($context);

        return $this->view->redirect('/account/security/authentication');
    }

    private function recoveryCodeStatus(ServerRequestInterface $request): ResponseInterface
    {
        $context = $this->guard->context($request);
        if ($context === null) {
            return $this->guard->rejection($request);
        }
        $set = $this->recoveryCodes->findActiveRecoveryCodeSet($context->accountInternalId);
        $csrf = $this->csrf->issue($request, CsrfAction::ACCOUNT_MFA_RECOVERY_CODES_REGENERATE);

        return $this->securePage($this->view->render(
            $request,
            'pages.recovery-codes-status',
            'fragments.recovery-codes-status',
            new ViewData([
                'csrf_token' => $csrf['token'],
                'remaining_codes' => $set->remainingCodes ?? 0,
                'can_regenerate' => $this->stepUpGuard->permits(
                    $context,
                    StepUpAction::MFA_REGENERATE_RECOVERY_CODES,
                ),
            ]),
            'title.recovery_codes',
            cookie: $csrf['cookie'],
        ));
    }

    private function regenerateRecoveryCodes(ServerRequestInterface $request): ResponseInterface
    {
        $context = $this->guard->context($request);
        if ($context === null) {
            return $this->guard->rejection($request);
        }
        $this->validatedForm($request, CsrfAction::ACCOUNT_MFA_RECOVERY_CODES_REGENERATE);

        return $this->recoveryCodesOneTime($request, $this->recoveryCodeRegeneration->regenerate($context)->codes);
    }

    /** @param list<string> $codes */
    private function recoveryCodesOneTime(ServerRequestInterface $request, array $codes): ResponseInterface
    {
        return $this->securePage($this->view->render(
            $request,
            'pages.recovery-codes-one-time',
            'fragments.recovery-codes-one-time',
            new ViewData(['codes' => $codes]),
            'title.recovery_codes',
        ));
    }

    private function stepUpCompletion(
        ServerRequestInterface $request,
        StepUpAuthenticationResult $result,
    ): ResponseInterface {
        if (!$result->succeeded || $result->action === null) {
            return $this->problem('STEP_UP_REJECTED', 422);
        }
        $response = $this->view->redirect($result->action->continuation());

        return $result->cookie === null ? $response : $this->cookies->apply($response, [$result->cookie]);
    }

    /** @return array<string, string> */
    private function validatedForm(ServerRequestInterface $request, CsrfAction $action): array
    {
        $form = $this->input->form($request);
        $csrf = $this->csrf->issue($request, $action);
        if (
            !$this->csrf->validates(
                $request,
                $action,
                $csrf['cookie'],
                $this->input->string($form, 'csrf_token', 512),
            )
        ) {
            throw new \DomainException('CSRF validation failed.');
        }

        return $form;
    }

    /** @return array<string, mixed> */
    private function validatedJson(ServerRequestInterface $request, CsrfAction $action): array
    {
        $body = $this->input->json($request, $this->configuration->webauthnMaximumResponseBytes);
        $csrf = $this->csrf->issue($request, $action);
        if (!$this->csrf->validates($request, $action, $csrf['cookie'], '')) {
            throw new \DomainException('CSRF validation failed.');
        }

        return $body;
    }

    /** @return array{array<string, mixed>, AuthenticationTransactionCookieValue} */
    private function jsonTransaction(ServerRequestInterface $request, CsrfAction $action): array
    {
        $body = $this->validatedJson($request, $action);
        $cookie = $this->transactionCookie($request);
        if ($cookie === null) {
            throw new \DomainException('Authentication transaction is unavailable.');
        }

        return [$body, $cookie];
    }

    private function transactionCookie(ServerRequestInterface $request): ?AuthenticationTransactionCookieValue
    {
        $raw = $request->getCookieParams()[$this->transactionCookies->name()] ?? null;
        if (!is_string($raw)) {
            return null;
        }
        try {
            return $this->transactionCookieParser->parse($raw);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /** @param array<string, mixed> $body */
    private function credentialJson(array $body): string
    {
        $credential = $body['credential'] ?? null;
        if (!is_array($credential)) {
            throw new \InvalidArgumentException('WebAuthn credential is invalid.');
        }

        return json_encode($credential, JSON_THROW_ON_ERROR);
    }

    /** @return array<string, string> */
    private function parameters(ServerRequestInterface $request): array
    {
        $parameters = $request->getAttribute(RouteAttributes::PARAMETERS);
        if (!is_array($parameters)) {
            return [];
        }
        $result = [];
        foreach ($parameters as $name => $value) {
            if (is_string($name) && is_string($value)) {
                $result[$name] = $value;
            }
        }

        return $result;
    }

    private function securePage(ResponseInterface $response): ResponseInterface
    {
        return $response
            ->withHeader('Cache-Control', 'private, no-store')
            ->withHeader('Referrer-Policy', 'no-referrer')
            ->withHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    private function problem(string $code, int $status): ResponseInterface
    {
        return $this->json->createProblem([
            'type' => 'about:blank',
            'title' => 'Authentication request rejected',
            'status' => $status,
            'code' => $code,
        ], $status);
    }
}
