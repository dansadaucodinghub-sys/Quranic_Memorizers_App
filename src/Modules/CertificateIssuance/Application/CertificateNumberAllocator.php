<?php

declare(strict_types=1);

namespace Qmdb\Modules\CertificateIssuance\Application;

interface CertificateNumberAllocator
{
    /** Must be called inside the authoritative preparation transaction. */
    public function allocate(int $workspaceId, int $year, string $scope): string;
}
