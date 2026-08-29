<?php

declare(strict_types=1);

namespace Qmdb\Shared\Presentation\View;

use Qmdb\Shared\Localization\Translator;
use Qmdb\Shared\Presentation\Html\SafeHtml;
use Qmdb\Shared\Presentation\Security\CspNonce;

final readonly class PageRenderer
{
    public function __construct(private PhpViewRenderer $renderer)
    {
    }

    public function render(
        string $page,
        ViewData $pageData,
        Translator $translator,
        CspNonce $nonce,
        string $titleKey,
        string $currentPath,
        ?TenantPresentationContext $tenant = null,
        bool $authenticated = false,
        int $tenantContextVersion = 0,
    ): SafeHtml {
        $content = $this->renderer->render($page, $pageData, $translator);

        return $this->renderer->render('layouts.application', new ViewData([
            'content' => $content,
            'title' => $translator->trans($titleKey),
            'locale' => $translator->locale()->value(),
            'direction' => $translator->locale()->direction()->value,
            'nonce' => $nonce->value(),
            'current_path' => $currentPath,
            'tenant_context_version' => max($tenant?->tenantContextVersion() ?? 0, $tenantContextVersion),
            'tenant_workspace_id' => $tenant?->workspacePublicId() ?? '',
            'tenant_workspace_name' => $tenant?->workspaceDisplayName() ?? '',
            'tenant_authenticated' => $authenticated,
        ]), $translator);
    }
}
