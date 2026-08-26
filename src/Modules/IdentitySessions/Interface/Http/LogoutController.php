<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Interface\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentitySessions\Application\AccountLogoutService;
use Qmdb\Modules\IdentitySessions\Application\AuthenticationCookieResponseDecorator;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Presentation\Response\FragmentRequestDetector;

final readonly class LogoutController implements Controller
{
    public function __construct(
        private IdentitySessionFormInput $input,
        private IdentityCsrf $csrf,
        private AuthenticatedRequestGuard $guard,
        private AccountLogoutService $logout,
        private IdentityAccessView $view,
        private AuthenticationCookieResponseDecorator $cookies,
        private FragmentRequestDetector $fragments,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $csrf = $this->csrf->issue($request, CsrfAction::ACCOUNT_LOGOUT);
        try {
            $submitted = $this->input->csrf($request);
        } catch (\Throwable) {
            return $this->view->redirect('/login')->withStatus(403);
        }
        if (!$this->csrf->validates($request, CsrfAction::ACCOUNT_LOGOUT, $csrf['cookie'], $submitted)) {
            return $this->view->redirect('/login')->withStatus(403);
        }
        $clear = $this->logout->logout($this->guard->context($request));
        $rotated = $this->csrf->rotate(CsrfAction::ACCOUNT_LOGIN);
        $response = $this->fragments->isFragment($request)
            ? $this->view->render(
                $request,
                'pages.login',
                'fragments.login-success',
                IdentitySessionViewDataFactory::completion(),
                'title.login',
            )->withHeader('X-QMDB-Navigate', '/login?logged_out=1')
            : $this->view->redirect('/login?logged_out=1');

        return $this->cookies->apply($response, [$clear], $rotated['cookie']);
    }
}
