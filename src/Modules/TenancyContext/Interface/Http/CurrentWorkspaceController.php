<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Interface\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Presentation\View\ViewData;
use Qmdb\Shared\Presentation\Response\FragmentRequestDetector;

final readonly class CurrentWorkspaceController implements Controller
{
    public function __construct(
        private AuthenticatedRequestGuard $authentication,
        private TenantContextRequiredGuard $tenant,
        private IdentityAccessView $view,
        private FragmentRequestDetector $fragments,
        private ResponseFactoryInterface $responses,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->authentication->context($request) === null) {
            return $this->authentication->rejection($request);
        }
        try {
            $context = $this->tenant->require($request);
        } catch (\Qmdb\Modules\TenancyContext\Application\Exception\TenantContextRequiredException $exception) {
            if ($this->fragments->isFragment($request)) {
                throw $exception;
            }

            return $this->responses->createResponse(303)
                ->withHeader('Location', '/account/workspaces?context_required=1')
                ->withHeader('Cache-Control', 'private, no-store');
        }

        return $this->view->render(
            $request,
            'pages.workspace',
            'fragments.workspace-panel',
            new ViewData([
                'workspace_id' => $context->workspaceId->toString(),
                'workspace_name' => $context->workspaceName,
                'workspace_status' => $context->workspaceStatus->value,
                'membership_status' => $context->membership->status->value,
                'tenant_context_version' => $context->version->value,
            ]),
            'title.workspace',
        )->withHeader('Cache-Control', 'private, no-store');
    }
}
