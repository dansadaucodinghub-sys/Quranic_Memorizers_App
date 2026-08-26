<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityWeb\Csrf;

use Psr\Http\Message\ServerRequestInterface;

final readonly class CsrfCookieFactory
{
    private string $name;

    public function __construct(private bool $productionLike, private int $ttlSeconds)
    {
        $this->name = $productionLike ? '__Host-qmdb_csrf' : 'qmdb_csrf';
    }

    public function resolve(ServerRequestInterface $request): CsrfCookie
    {
        $cookies = $request->getCookieParams();
        $candidate = $cookies[$this->name] ?? null;
        if (is_string($candidate)) {
            try {
                return new CsrfCookie(new CsrfCookieNonce($candidate), null);
            } catch (\InvalidArgumentException) {
            }
        }
        return $this->fresh();
    }

    public function fresh(): CsrfCookie
    {
        $nonce = CsrfCookieNonce::generate();
        $header = $this->name . '=' . $nonce->value() . '; Path=/; Max-Age=' . $this->ttlSeconds
            . '; HttpOnly; SameSite=Strict' . ($this->productionLike ? '; Secure' : '');

        return new CsrfCookie($nonce, $header);
    }
}
