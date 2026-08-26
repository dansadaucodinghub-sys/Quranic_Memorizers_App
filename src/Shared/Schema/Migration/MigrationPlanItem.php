<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Migration;

final readonly class MigrationPlanItem
{
    public function __construct(
        public string $id,
        public string $description,
        public MigrationPlanStatus $status,
    ) {
    }
}
