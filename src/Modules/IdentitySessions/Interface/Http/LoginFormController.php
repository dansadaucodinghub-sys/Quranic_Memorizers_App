<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Interface\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentitySessions\Domain\LoginSubmissionId;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Shared\Http\Contract\Controller;

final readonly class LoginFormController implements Controller
{
    public function __construct(
        private AuthenticatedRequestGuard $guard,
        private IdentityCsrf $csrf,
        private IdentityAccessView $view,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->guard->context($request) !== null) {
            return $this->view->redirect('/account/security/sessions');
        }
        $csrf = $this->csrf->issue($request, CsrfAction::ACCOUNT_LOGIN);

        return $this->view->render(
            $request,
            'pages.login',
            'fragments.login-form',
            IdentitySessionViewDataFactory::login(
                $csrf['token'],
                LoginSubmissionId::generate()->toString(),
            ),
            'title.login',
            cookie: $csrf['cookie'],
        );
    }
}
