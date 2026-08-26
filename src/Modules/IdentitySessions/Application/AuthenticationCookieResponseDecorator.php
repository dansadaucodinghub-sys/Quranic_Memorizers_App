<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Application;

use Psr\Http\Message\ResponseInterface;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfCookie;

final readonly class AuthenticationCookieResponseDecorator
{
    /** @param list<AuthenticationCookieInstruction> $instructions */
    public function apply(
        ResponseInterface $response,
        array $instructions,
        ?CsrfCookie $csrfCookie = null,
    ): ResponseInterface {
        foreach ($instructions as $instruction) {
            $response = $response->withAddedHeader('Set-Cookie', $instruction->revealForResponse());
        }
        if ($csrfCookie?->setCookieHeader !== null) {
            $response = $response->withAddedHeader('Set-Cookie', $csrfCookie->setCookieHeader);
        }

        return $response;
    }
}
