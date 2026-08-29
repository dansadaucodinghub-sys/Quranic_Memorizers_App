<?php

declare(strict_types=1);

namespace Qmdb\Shared\Presentation\View;

use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Shared\Http\Request\RequestContextAttributes;
use Qmdb\Shared\Localization\LocaleContext;
use Qmdb\Shared\Localization\TranslationCatalog;
use Qmdb\Shared\Localization\Translator;
use Qmdb\Shared\Presentation\Security\CspNonce;
use RuntimeException;

final readonly class PresentationRequestContext
{
    public function __construct(private TranslationCatalog $catalog)
    {
    }

    /**
     * @return array{
     *   translator: Translator,
     *   nonce: CspNonce,
     *   path: string,
     *   tenant: ?TenantPresentationContext,
     *   tenant_context_version: int,
     *   authenticated: bool
     * }
     */
    public function fromRequest(ServerRequestInterface $request): array
    {
        $locale = $request->getAttribute(RequestContextAttributes::LOCALE);
        $nonce = $request->getAttribute(RequestContextAttributes::CSP_NONCE);
        if (!$locale instanceof LocaleContext || !$nonce instanceof CspNonce) {
            throw new RuntimeException('Presentation request context is unavailable.');
        }

        return [
            'translator' => new Translator($this->catalog, $locale->locale()),
            'nonce' => $nonce,
            'path' => $request->getUri()->getPath() ?: '/',
            'tenant' => ($tenant = $request->getAttribute('qmdb.tenant_context')) instanceof TenantPresentationContext
                ? $tenant
                : null,
            'tenant_context_version' => is_int($request->getAttribute('qmdb.tenant_context_version'))
                ? $request->getAttribute('qmdb.tenant_context_version') : 0,
            'authenticated' => is_object($request->getAttribute('qmdb.authenticated_account')),
        ];
    }
}
