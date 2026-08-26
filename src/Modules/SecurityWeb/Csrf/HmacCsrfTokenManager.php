<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityWeb\Csrf;

use DateTimeImmutable;
use InvalidArgumentException;
use SensitiveParameter;

final readonly class HmacCsrfTokenManager implements CsrfTokenManager
{
    public function __construct(#[SensitiveParameter] private string $key, private int $ttlSeconds)
    {
        if (strlen($key) < 32 || $ttlSeconds < 1) {
            throw new InvalidArgumentException('CSRF token configuration is invalid.');
        }
    }

    public function issue(CsrfAction $action, CsrfCookieNonce $nonce, DateTimeImmutable $now): CsrfToken
    {
        $issued = $now->getTimestamp();
        $signature = $this->signature($action, $nonce, $issued);

        return new CsrfToken('v1.' . $issued . '.' . $signature);
    }

    public function verify(
        CsrfAction $action,
        CsrfCookieNonce $nonce,
        CsrfToken $token,
        DateTimeImmutable $now,
    ): CsrfTokenVerificationResult {
        $parts = explode('.', $token->value());
        $issued = isset($parts[1]) && preg_match('/\A[0-9]{1,12}\z/', $parts[1]) === 1 ? (int)$parts[1] : 0;
        $signature = $parts[2] ?? '';
        $age = $now->getTimestamp() - $issued;
        if ($issued < 1 || $age < 0 || $age > $this->ttlSeconds) {
            return CsrfTokenVerificationResult::INVALID;
        }

        return hash_equals($this->signature($action, $nonce, $issued), $signature)
            ? CsrfTokenVerificationResult::VALID
            : CsrfTokenVerificationResult::INVALID;
    }

    private function signature(CsrfAction $action, CsrfCookieNonce $nonce, int $issued): string
    {
        $binary = hash_hmac(
            'sha256',
            "QMDB-CSRF\0" . $action->value . "\0" . $issued . "\0" . $nonce->value(),
            $this->key,
            true,
        );

        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    /** @return array{key: string} */
    public function __debugInfo(): array
    {
        return ['key' => '[REDACTED]'];
    }
}
