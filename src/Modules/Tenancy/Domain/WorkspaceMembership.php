<?php

declare(strict_types=1);

namespace Qmdb\Modules\Tenancy\Domain;

use DateTimeImmutable;
use InvalidArgumentException;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class WorkspaceMembership
{
    public function __construct(
        public ?int $internalId,
        public UuidV7 $publicId,
        public int $workspaceInternalId,
        public int $accountInternalId,
        public MembershipStatus $status,
        public int $version,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
        if ($workspaceInternalId < 1 || $accountInternalId < 1 || $version < 1) {
            throw new InvalidArgumentException('Membership relational state is invalid.');
        }
    }
}
