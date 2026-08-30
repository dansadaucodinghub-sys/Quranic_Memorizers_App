<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccountState\Domain;

final readonly class AccountStateReference
{
    public function __construct(public ?string $value, int $maximumBytes)
    {
        if ($value !== null && (strlen($value) > $maximumBytes || preg_match('/\\A[ A-Za-z0-9._:\\/-]+\\z/', $value) !== 1)) {
            throw new \InvalidArgumentException('Account-state reference is invalid.');
        }
    }
}
