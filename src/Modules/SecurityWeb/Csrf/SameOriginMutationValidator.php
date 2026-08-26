<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityWeb\Csrf;

use DateTimeImmutable;
use Psr\Http\Message\ServerRequestInterface;

final readonly class SameOriginMutationValidator
{
    public function __construct(private CsrfTokenManager $tokens, private string $expectedOrigin)
    {
    }

    public function validate(
        ServerRequestInterface $request,
        CsrfAction $action,
        CsrfCookieNonce $nonce,
        string $submittedToken,
        DateTimeImmutable $now,
    ): bool {
        $origin = trim($request->getHeaderLine('Origin'));
        if ($origin !== '' && !hash_equals($this->expectedOrigin, strtolower(rtrim($origin, '/')))) {
            return false;
        }
        try {
            $token = new CsrfToken($submittedToken);
        } catch (\InvalidArgumentException) {
            return false;
        }

        return $this->tokens->verify($action, $nonce, $token, $now) === CsrfTokenVerificationResult::VALID;
    }
}
