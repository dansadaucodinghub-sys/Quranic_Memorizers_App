<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Application;

/** A private internal identity group.  It contains no names or demographics. */
final readonly class PersonCanonicalIdentityGroup
{
    /** @param list<int> $sourcePersonIds */
    public function __construct(public int $canonicalPersonId, public array $sourcePersonIds)
    {
    }
}
