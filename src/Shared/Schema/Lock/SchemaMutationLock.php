<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Lock;

interface SchemaMutationLock
{
    public function acquire(): SchemaLockHandle;

    public function release(SchemaLockHandle $handle): void;
}
