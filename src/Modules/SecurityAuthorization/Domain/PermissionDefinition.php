<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain;

use DateTimeImmutable;
use InvalidArgumentException;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;

final readonly class PermissionDefinition
{
    public function __construct(
        public PermissionId $id,
        public PermissionCode $code,
        public AuthorizationScopeType $scopeType,
        public AuthenticationAssuranceLevel $requiredAssurance,
        public PermissionStatus $status,
        public string $owningModule,
        public int $version,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?DateTimeImmutable $retiredAt = null,
    ) {
        if (
            $version < 1
            || preg_match('/\A[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+\z/D', $owningModule) !== 1
            || ($status === PermissionStatus::RETIRED) !== ($retiredAt !== null)
        ) {
            throw new InvalidArgumentException('Permission definition is invalid.');
        }
    }
}
