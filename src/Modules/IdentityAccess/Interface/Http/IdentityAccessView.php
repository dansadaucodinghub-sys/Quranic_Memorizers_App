<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Interface\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfCookie;
use Qmdb\Shared\Presentation\Response\PageOrFragmentResponseFactory;
use Qmdb\Shared\Presentation\View\PageRenderer;
use Qmdb\Shared\Presentation\View\PhpViewRenderer;
use Qmdb\Shared\Presentation\View\PresentationRequestContext;
use Qmdb\Shared\Presentation\View\ViewData;

final readonly class IdentityAccessView
{
    public function __construct(
        private PresentationRequestContext $context,
        private PhpViewRenderer $views,
        private PageRenderer $pages,
        private PageOrFragmentResponseFactory $responses,
        private ResponseFactoryInterface $responseFactory,
    ) {
    }

    public function render(
        ServerRequestInterface $request,
        string $page,
        string $fragment,
        ViewData $data,
        string $titleKey,
        int $status = 200,
        ?CsrfCookie $cookie = null,
        bool $verificationHeaders = false,
    ): ResponseInterface {
        $context = $this->context->fromRequest($request);
        $fragmentHtml = $this->views->render($fragment, $data, $context['translator']);
        $pageHtml = $this->pages->render(
            $page,
            $data,
            $context['translator'],
            $context['nonce'],
            $titleKey,
            $context['path'],
            $context['tenant'],
            $context['authenticated'],
            $context['tenant_context_version'],
        );
        $response = $this->responses->create($request, $pageHtml, $fragmentHtml, $status);

        return $this->secure($response, $cookie, $verificationHeaders);
    }

    public function redirect(string $path, ?CsrfCookie $cookie = null): ResponseInterface
    {
        return $this->secure(
            $this->responseFactory->createResponse(303)
                ->withHeader('Location', $path)
                ->withHeader('Cache-Control', 'no-store'),
            $cookie,
            false,
        );
    }

    public function secure(
        ResponseInterface $response,
        ?CsrfCookie $cookie,
        bool $verificationHeaders,
    ): ResponseInterface {
        if ($cookie?->setCookieHeader !== null) {
            $response = $response->withAddedHeader('Set-Cookie', $cookie->setCookieHeader);
        }
        if ($verificationHeaders) {
            $response = $response
                ->withHeader('Cache-Control', 'no-store')
                ->withHeader('Referrer-Policy', 'no-referrer')
                ->withHeader('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
