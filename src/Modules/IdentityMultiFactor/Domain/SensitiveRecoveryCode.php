<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Domain;

use JsonSerializable;
use LogicException;
use SensitiveParameter;

final readonly class SensitiveRecoveryCode implements JsonSerializable
{
    public function __construct(#[SensitiveParameter] private string $displayValue)
    {
        if (preg_match('/\A[0-9A-HJKMNP-TV-Z]+(?:-[0-9A-HJKMNP-TV-Z]+)+\z/', $displayValue) !== 1) {
            throw new \InvalidArgumentException('Recovery code is invalid.');
        }
    }

    public function revealOnce(): string
    {
        return $this->displayValue;
    }

    public function normalized(): NormalizedRecoveryCode
    {
        return NormalizedRecoveryCode::fromInput($this->displayValue);
    }

    /** @return array{code: string} */
    public function __debugInfo(): array
    {
        return ['code' => '[REDACTED]'];
    }

    public function jsonSerialize(): never
    {
        throw new LogicException('Recovery code cannot be serialized.');
    }

    public function __serialize(): never
    {
        throw new LogicException('Recovery code cannot be serialized.');
    }
}
