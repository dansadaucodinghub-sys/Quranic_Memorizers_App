<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Schema;

use Qmdb\Shared\Schema\Lock\SchemaLockHandle;
use Qmdb\Shared\Schema\Lock\SchemaMutationLock;

final class RecordingSchemaMutationLock implements SchemaMutationLock
{
    public int $acquisitions = 0;
    public int $releases = 0;

    public function acquire(): SchemaLockHandle
    {
        $this->acquisitions++;

        return new SchemaLockHandle('qmdb:schema:test');
    }

    public function release(SchemaLockHandle $handle): void
    {
        $handle->markReleased();
        $this->releases++;
    }
}
