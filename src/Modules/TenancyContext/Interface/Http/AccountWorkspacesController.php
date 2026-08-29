<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Interface\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Modules\Tenancy\Domain\Value\WorkspaceId;
use Qmdb\Modules\TenancyContext\Application\AccountWorkspaceInventoryHandler;
use Qmdb\Modules\TenancyContext\Application\AccountWorkspaceInventoryQuery;
use Qmdb\Shared\Http\Contract\Controller;

final readonly class AccountWorkspacesController implements Controller
{
    public function __construct(
        private AuthenticatedRequestGuard $guard,
        private AccountWorkspaceInventoryHandler $inventory,
        private IdentityCsrf $csrf,
        private IdentityAccessView $view,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $account = $this->guard->context($request);
        if ($account === null) {
            return $this->guard->rejection($request);
        }
        $switch = $this->csrf->issue($request, CsrfAction::ACCOUNT_WORKSPACE_SWITCH);
        $query = $request->getQueryParams();
        try {
            $cursor = is_string($query['cursor'] ?? null) && $query['cursor'] !== ''
                ? WorkspaceId::fromString($query['cursor']) : null;
        } catch (\InvalidArgumentException) {
            $cursor = null;
        }
        $data = TenantContextViewDataFactory::inventory(
            $this->inventory->handle(new AccountWorkspaceInventoryQuery($account, 25, $cursor)),
            $switch['token'],
            $this->csrf->issueForCookie($switch['cookie'], CsrfAction::ACCOUNT_WORKSPACE_CLEAR),
        );

        return $this->view->render(
            $request,
            'pages.account-workspaces',
            'fragments.account-workspaces-panel',
            $data,
            'title.account_workspaces',
            cookie: $switch['cookie'],
        )->withHeader('Cache-Control', 'private, no-store');
    }
}
