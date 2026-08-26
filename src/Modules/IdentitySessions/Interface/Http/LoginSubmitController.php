<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Interface\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityRequestContext;
use Qmdb\Modules\IdentitySessions\Application\AccountLoginCommand;
use Qmdb\Modules\IdentitySessions\Application\AccountLoginOutcome;
use Qmdb\Modules\IdentitySessions\Application\AccountLoginService;
use Qmdb\Modules\IdentitySessions\Application\AuthenticationCookieResponseDecorator;
use Qmdb\Modules\IdentitySessions\Application\DeviceCookieFactory;
use Qmdb\Modules\IdentitySessions\Domain\LoginSubmissionId;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Presentation\Response\FragmentRequestDetector;

final readonly class LoginSubmitController implements Controller
{
    public function __construct(
        private IdentitySessionFormInput $input,
        private IdentityCsrf $csrf,
        private IdentityAccessView $view,
        private IdentityRequestContext $requestContext,
        private AuthenticatedRequestGuard $guard,
        private AccountLoginService $login,
        private DeviceCookieFactory $deviceCookies,
        private AuthenticationCookieResponseDecorator $cookies,
        private FragmentRequestDetector $fragments,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $csrf = $this->csrf->issue($request, CsrfAction::ACCOUNT_LOGIN);
        try {
            [$email, $password, $submission, $csrfToken] = $this->input->login($request);
        } catch (UnsupportedMediaTypeException) {
            return $this->form($request, $csrf, 415, '', 'form.error.media_type');
        } catch (\InvalidArgumentException) {
            return $this->form($request, $csrf, 422, '', 'login.error.invalid');
        }
        if (!$this->csrf->validates($request, CsrfAction::ACCOUNT_LOGIN, $csrf['cookie'], $csrfToken)) {
            return $this->form($request, $csrf, 403, $email, 'form.error.csrf');
        }
        if (!$this->idempotencyMatches($request, $submission->toString())) {
            return $this->form($request, $csrf, 409, $email, 'form.error.idempotency');
        }
        $rawDevice = $request->getCookieParams()[$this->deviceCookies->name()] ?? null;
        $result = $this->login->login(new AccountLoginCommand(
            $email,
            $password,
            $submission,
            $this->requestContext->peer($request),
            is_string($rawDevice) ? $rawDevice : null,
            $this->guard->context($request),
        ));
        if ($result->outcome === AccountLoginOutcome::THROTTLED) {
            return $this->form($request, $csrf, 429, $email, 'form.error.throttled')
                ->withHeader('Retry-After', (string)$result->retryAfterSeconds);
        }
        if ($result->outcome === AccountLoginOutcome::INVALID_CREDENTIALS) {
            return $this->form($request, $csrf, 422, $email, 'login.error.invalid');
        }
        if ($result->outcome === AccountLoginOutcome::REPLAYED) {
            return $this->form($request, $csrf, 409, $email, 'form.error.idempotency');
        }
        $rotatedCsrf = $this->csrf->rotate(CsrfAction::ACCOUNT_LOGIN);
        $response = $this->fragments->isFragment($request)
            ? $this->view->render(
                $request,
                'pages.login',
                'fragments.login-success',
                IdentitySessionViewDataFactory::completion(),
                'title.login',
            )->withHeader('X-QMDB-Navigate', '/account/security/sessions')
            : $this->view->redirect('/account/security/sessions');

        return $this->cookies->apply($response, $result->cookieInstructions, $rotatedCsrf['cookie']);
    }

    /** @param array{cookie: \Qmdb\Modules\SecurityWeb\Csrf\CsrfCookie, token: string} $csrf */
    private function form(
        ServerRequestInterface $request,
        array $csrf,
        int $status,
        string $email,
        string $error,
    ): ResponseInterface {
        return $this->view->render(
            $request,
            'pages.login',
            'fragments.login-form',
            IdentitySessionViewDataFactory::login(
                $csrf['token'],
                LoginSubmissionId::generate()->toString(),
                $email,
                $error,
            ),
            'title.login',
            $status,
            $csrf['cookie'],
        );
    }

    private function idempotencyMatches(ServerRequestInterface $request, string $submissionId): bool
    {
        if (!$this->fragments->isFragment($request)) {
            return true;
        }
        $header = trim($request->getHeaderLine('Idempotency-Key'));

        return $header !== '' && hash_equals($submissionId, $header);
    }
}
