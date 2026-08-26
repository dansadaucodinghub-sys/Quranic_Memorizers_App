<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Qmdb\Shared\Configuration\ApplicationEnvironment;
use Qmdb\Shared\Http\Request\RequestContextAttributes;
use Qmdb\Shared\Presentation\Response\FragmentRequestDetector;
use Qmdb\Shared\Presentation\Security\CspNonce;

final readonly class SecurityHeadersMiddleware implements MiddlewareInterface
{
    /** @var array<string, string> */
    private const REQUIRED_HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'no-referrer',
        'X-Frame-Options' => 'DENY',
        'X-Permitted-Cross-Domain-Policies' => 'none',
        'Cross-Origin-Resource-Policy' => 'same-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
    ];

    public function __construct(private ApplicationEnvironment $environment)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request)
            ->withoutHeader('X-Powered-By')
            ->withoutHeader('Strict-Transport-Security');

        foreach (self::REQUIRED_HEADERS as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        $contentType = strtolower($response->getHeaderLine('Content-Type'));
        $isHtml = str_starts_with($contentType, 'text/html')
            || str_starts_with($contentType, FragmentRequestDetector::MEDIA_TYPE);
        $response = $response->withHeader(
            'Content-Security-Policy',
            $isHtml ? $this->htmlPolicy($request) : $this->nonDocumentPolicy(),
        );

        if ($this->environment->isProductionLike() && strtolower($request->getUri()->getScheme()) === 'https') {
            $response = $response->withHeader(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains',
            );
        }

        return $response;
    }

    private function htmlPolicy(ServerRequestInterface $request): string
    {
        $nonce = $request->getAttribute(RequestContextAttributes::CSP_NONCE);
        if (!$nonce instanceof CspNonce) {
            return $this->nonDocumentPolicy();
        }

        return "default-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'; "
            . "object-src 'none'; script-src 'self' 'nonce-{$nonce->value()}'; script-src-attr 'none'; "
            . "style-src 'self'; style-src-attr 'none'; img-src 'self' data:; font-src 'self'; "
            . "connect-src 'self'; media-src 'self'; manifest-src 'self'; worker-src 'none'; frame-src 'none'";
    }

    private function nonDocumentPolicy(): string
    {
        return "default-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'none'";
    }
}
