<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Interface\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\IdentityAccess\Domain\RegistrationSubmissionId;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Shared\Http\Contract\Controller;

final readonly class AccountRegistrationFormController implements Controller
{
    public function __construct(private IdentityCsrf $csrf, private IdentityAccessView $view)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $csrf = $this->csrf->issue($request, CsrfAction::ACCOUNT_REGISTER);

        return $this->view->render(
            $request,
            'pages.account-register',
            'fragments.account-register-form',
            IdentityViewDataFactory::registration(
                $csrf['token'],
                RegistrationSubmissionId::generate()->toString(),
            ),
            'title.registration',
            cookie: $csrf['cookie'],
        );
    }
}
