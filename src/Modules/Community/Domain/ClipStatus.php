<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Domain;

enum ClipStatus: string
{
    case DRAFT = 'DRAFT';
    case REVIEW_PENDING = 'REVIEW_PENDING';
    case PUBLISHED = 'PUBLISHED';
    case HIDDEN = 'HIDDEN';
    case REMOVED = 'REMOVED';
    case SUPERSEDED = 'SUPERSEDED';
    case ARCHIVED = 'ARCHIVED';

    public function permits(self $next): bool
    {
        return match ($this) {
            self::DRAFT => in_array($next, [self::REVIEW_PENDING, self::ARCHIVED], true),
            self::REVIEW_PENDING => in_array($next, [self::DRAFT, self::PUBLISHED, self::HIDDEN], true),
            self::PUBLISHED => in_array($next, [self::HIDDEN, self::REMOVED, self::SUPERSEDED], true),
            self::HIDDEN => in_array($next, [self::PUBLISHED, self::REMOVED, self::ARCHIVED], true),
            self::REMOVED => in_array($next, [self::HIDDEN, self::ARCHIVED], true),
            self::SUPERSEDED, self::ARCHIVED => false,
        };
    }

    public function assertTransition(self $next): void
    {
        if (!$this->permits($next)) {
            throw new \DomainException('Recitation Clip lifecycle transition is not permitted.');
        }
    }
}
