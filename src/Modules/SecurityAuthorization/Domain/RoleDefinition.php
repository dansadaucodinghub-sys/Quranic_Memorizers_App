<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class RoleDefinition
{
    public function __construct(
        public RoleId $id,
        public RoleCode $code,
        public AuthorizationScopeType $scopeType,
        public RoleStatus $status,
        public bool $isSystem,
        public int $version,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?DateTimeImmutable $retiredAt = null,
    ) {
        if ($version < 1 || ($status === RoleStatus::RETIRED) !== ($retiredAt !== null)) {
            throw new InvalidArgumentException('Role definition is invalid.');
        }
    }
}
