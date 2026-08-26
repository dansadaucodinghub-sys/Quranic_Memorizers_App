<?php

declare(strict_types=1);

namespace Qmdb\Shared\Database\Health;

interface DatabaseHealthCheck
{
    public function check(): DatabaseHealthReport;
}
