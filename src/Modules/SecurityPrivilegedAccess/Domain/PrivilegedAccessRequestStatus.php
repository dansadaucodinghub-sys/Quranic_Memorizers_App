<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityPrivilegedAccess\Domain;

enum PrivilegedAccessRequestStatus: string
{
    case REQUESTED = 'REQUESTED';
    case PARTIALLY_APPROVED = 'PARTIALLY_APPROVED';
    case APPROVED = 'APPROVED';
    case ACTIVE = 'ACTIVE';
    case REJECTED = 'REJECTED';
    case CANCELLED = 'CANCELLED';
    case REVOKED = 'REVOKED';
    case EXPIRED = 'EXPIRED';
    case REVIEW_REQUIRED = 'REVIEW_REQUIRED';
    case CLOSED = 'CLOSED';

    public function permits(self $next, PrivilegedAccessType $type): bool
    {
        $allowed = match ($type) {
            PrivilegedAccessType::TEMPORARY_PRIVILEGE => [
                self::REQUESTED->value => [self::APPROVED, self::REJECTED, self::CANCELLED],
                self::APPROVED->value => [self::ACTIVE, self::REVOKED, self::EXPIRED],
                self::ACTIVE->value => [self::CLOSED, self::REVOKED, self::EXPIRED],
            ],
            PrivilegedAccessType::SUPPORT_ACCESS => [
                self::REQUESTED->value => [self::PARTIALLY_APPROVED, self::REJECTED, self::CANCELLED],
                self::PARTIALLY_APPROVED->value => [self::APPROVED, self::REJECTED, self::CANCELLED],
                self::APPROVED->value => [self::ACTIVE, self::REVOKED, self::EXPIRED],
                self::ACTIVE->value => [self::REVIEW_REQUIRED, self::REVOKED, self::EXPIRED],
                self::REVIEW_REQUIRED->value => [self::CLOSED],
            ],
            PrivilegedAccessType::BREAK_GLASS => [
                self::ACTIVE->value => [self::REVIEW_REQUIRED, self::REVOKED, self::EXPIRED],
                self::REVIEW_REQUIRED->value => [self::CLOSED],
            ],
        };

        return in_array($next, $allowed[$this->value] ?? [], true);
    }
}
