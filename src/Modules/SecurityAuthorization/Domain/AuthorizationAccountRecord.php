<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain;

use Qmdb\Modules\Identity\Domain\Value\AccountId;

final readonly class AuthorizationAccountRecord
{
    public function __construct(
        public int $internalId,
        public AccountId $id,
        public bool $active,
    ) {
        if ($internalId < 1) {
            throw new \InvalidArgumentException('Authorization account record is invalid.');
        }
    }
}
