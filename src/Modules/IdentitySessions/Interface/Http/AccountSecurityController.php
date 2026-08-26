<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Interface\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentitySessions\Application\AccountSessionInventoryHandler;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Shared\Http\Contract\Controller;

final readonly class AccountSecurityController implements Controller
{
    public function __construct(
        private AuthenticatedRequestGuard $guard,
        private AccountSessionInventoryHandler $inventory,
        private IdentityCsrf $csrf,
        private IdentityAccessView $view,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $context = $this->guard->context($request);
        if ($context === null) {
            return $this->guard->rejection($request);
        }
        $csrf = $this->csrf->issue($request, CsrfAction::ACCOUNT_LOGOUT);
        $data = IdentitySessionViewDataFactory::inventory(
            $this->inventory->handle($context),
            $csrf['token'],
            $this->csrf->issueForCookie($csrf['cookie'], CsrfAction::ACCOUNT_SESSION_REVOKE),
            $this->csrf->issueForCookie($csrf['cookie'], CsrfAction::ACCOUNT_DEVICE_REVOKE),
        );

        return $this->view->render(
            $request,
            'pages.account-security-sessions',
            'fragments.account-security-session-panel',
            $data,
            'title.account_security',
            cookie: $csrf['cookie'],
        )->withHeader('Cache-Control', 'private, no-store');
    }
}
