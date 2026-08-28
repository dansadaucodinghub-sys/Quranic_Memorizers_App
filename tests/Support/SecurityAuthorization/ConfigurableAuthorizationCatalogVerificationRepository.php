<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\SecurityAuthorization;

use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationCatalogVerificationReport;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationCatalog;
use Qmdb\Modules\SecurityAuthorization\Domain\Repository\AuthorizationCatalogVerificationRepository;

final class ConfigurableAuthorizationCatalogVerificationRepository implements AuthorizationCatalogVerificationRepository
{
    public function __construct(public AuthorizationCatalogVerificationReport $report)
    {
    }

    public int $calls = 0;

    public function verify(AuthorizationCatalog $catalog): AuthorizationCatalogVerificationReport
    {
        $this->calls++;

        return $this->report;
    }
}
