<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Interface\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Shared\Http\Contract\Controller;

final readonly class AccountRegistrationAcceptedController implements Controller
{
    public function __construct(private IdentityAccessView $view)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->view->render(
            $request,
            'pages.account-registration-accepted',
            'fragments.account-registration-accepted',
            IdentityViewDataFactory::empty(),
            'title.registration.accepted',
        );
    }
}
