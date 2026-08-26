<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Job;

use Qmdb\Shared\Identifier\RuntimeIdentifierGenerator;

final readonly class BackgroundJobIdGenerator
{
    public function __construct(private RuntimeIdentifierGenerator $generator)
    {
    }

    public function generate(): BackgroundJobId
    {
        return new BackgroundJobId($this->generator->generate()->value());
    }
}
