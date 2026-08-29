<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Interface\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Modules\TenancyContext\Application\AccountWorkspaceInventoryHandler;
use Qmdb\Modules\TenancyContext\Application\AccountWorkspaceInventoryQuery;
use Qmdb\Modules\TenancyContext\Application\WorkspaceContextSelectionCommand;
use Qmdb\Modules\TenancyContext\Application\WorkspaceContextSelectionService;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Request\RequestContextAttributes;
use Qmdb\Shared\Observability\Correlation\CorrelationId;
use Qmdb\Shared\Presentation\Response\FragmentRequestDetector;

final readonly class WorkspaceSwitchController implements Controller
{
    public function __construct(
        private AuthenticatedRequestGuard $guard,
        private TenantContextFormInput $input,
        private WorkspaceContextSelectionService $selection,
        private AccountWorkspaceInventoryHandler $inventory,
        private IdentityCsrf $csrf,
        private IdentityAccessView $view,
        private FragmentRequestDetector $fragments,
        private ResponseFactoryInterface $responses,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $account = $this->guard->context($request);
        if ($account === null) {
            return $this->guard->rejection($request);
        }
        $csrf = $this->csrf->issue($request, CsrfAction::ACCOUNT_WORKSPACE_SWITCH);
        try {
            [$workspaceId, $expectedVersion, $token] = $this->input->selection($request);
        } catch (\DomainException) {
            return $this->responses->createResponse(415);
        } catch (\InvalidArgumentException) {
            return $this->responses->createResponse(422);
        }
        if (!$this->csrf->validates($request, CsrfAction::ACCOUNT_WORKSPACE_SWITCH, $csrf['cookie'], $token)) {
            return $this->responses->createResponse(403);
        }
        $correlationId = $request->getAttribute(RequestContextAttributes::REQUEST_ID);
        $selected = $this->selection->execute(new WorkspaceContextSelectionCommand(
            $account,
            $workspaceId,
            $expectedVersion,
            $correlationId instanceof CorrelationId ? $correlationId : null,
        ))->context;
        if (!$this->fragments->isFragment($request)) {
            return $this->view->redirect('/workspace', $csrf['cookie'])
                ->withHeader('X-QMDB-Tenant-Context-Version', (string)$selected->version->value);
        }
        $fresh = $this->inventory->handle(new AccountWorkspaceInventoryQuery($account));
        $data = TenantContextViewDataFactory::inventory(
            $fresh,
            $this->csrf->issueForCookie($csrf['cookie'], CsrfAction::ACCOUNT_WORKSPACE_SWITCH),
            $this->csrf->issueForCookie($csrf['cookie'], CsrfAction::ACCOUNT_WORKSPACE_CLEAR),
        );

        return $this->view->render(
            $request,
            'pages.account-workspaces',
            'fragments.account-workspaces-panel',
            $data,
            'title.account_workspaces',
            cookie: $csrf['cookie'],
        )->withHeader('Cache-Control', 'private, no-store')
            ->withHeader('X-QMDB-Navigate', '/workspace')
            ->withHeader('X-QMDB-Tenant-Context-Version', (string)$selected->version->value);
    }
}
