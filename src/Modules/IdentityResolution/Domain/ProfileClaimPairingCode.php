<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Domain;

use InvalidArgumentException;

final readonly class ProfileClaimPairingCode
{
    private function __construct(public string $selector, public string $secret)
    {
    }

    public static function issue(string $selector, string $secret): self
    {
        if (preg_match('/\A[A-Z0-9]{12}\z/D', $selector) !== 1 || preg_match('/\A[A-Za-z0-9_-]{22,64}\z/D', $secret) !== 1) {
            throw new InvalidArgumentException('Profile claim pairing code is invalid.');
        }

        return new self($selector, $secret);
    }

    public static function parse(string $code): ?self
    {
        if (preg_match('/\AQMPC-([A-Z0-9]{12})-([A-Za-z0-9_-]{22,64})\z/D', trim($code), $matches) !== 1) {
            return null;
        }

        return new self($matches[1], $matches[2]);
    }

    public function displayOnce(): string
    {
        return 'QMPC-' . $this->selector . '-' . $this->secret;
    }
}
