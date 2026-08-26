<?php

declare(strict_types=1);

namespace Qmdb\Shared\Application\Query;

interface QueryBus
{
    public function ask(Query $query): mixed;
}
