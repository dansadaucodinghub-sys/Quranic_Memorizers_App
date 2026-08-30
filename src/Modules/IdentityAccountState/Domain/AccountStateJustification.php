<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccountState\Domain;

final readonly class AccountStateJustification
{
    public function __construct(public string $value, int $maximumBytes)
    {
        if (
            $value === '' || strlen($value) > $maximumBytes || !mb_check_encoding($value, 'UTF-8')
            || preg_match('/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F\\x7F]/u', $value) === 1 || preg_match('/<[^>]+>/', $value) === 1
        ) {
            throw new \InvalidArgumentException('Account-state justification is invalid.');
        }
    }
}
