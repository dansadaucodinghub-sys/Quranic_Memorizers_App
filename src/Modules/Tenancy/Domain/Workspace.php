<?php

declare(strict_types=1);

namespace Qmdb\Modules\Tenancy\Domain;

use DateTimeImmutable;
use InvalidArgumentException;
use Qmdb\Modules\Tenancy\Domain\Value\WorkspaceId;

final readonly class Workspace
{
    public function __construct(
        public ?int $internalId,
        public WorkspaceId $publicId,
        public string $code,
        public string $name,
        public WorkspaceStatus $status,
        public int $version,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
        if (preg_match('/\A[a-z0-9](?:[a-z0-9-]{1,62}[a-z0-9])?\z/', $code) !== 1) {
            throw new InvalidArgumentException('Workspace code is invalid.');
        }
        if (trim($name) === '' || mb_strlen($name) > 191 || $version < 1) {
            throw new InvalidArgumentException('Workspace state is invalid.');
        }
    }
}
