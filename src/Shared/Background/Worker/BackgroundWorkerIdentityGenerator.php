<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Worker;

use Qmdb\Shared\Identifier\RuntimeIdentifierGenerator;

final readonly class BackgroundWorkerIdentityGenerator
{
    public function __construct(private RuntimeIdentifierGenerator $generator)
    {
    }

    public function generate(): BackgroundWorkerIdentity
    {
        return new BackgroundWorkerIdentity($this->generator->generate()->value());
    }
}
