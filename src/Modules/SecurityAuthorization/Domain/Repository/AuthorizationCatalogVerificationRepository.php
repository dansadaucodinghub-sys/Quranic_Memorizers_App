<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain\Repository;

use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationCatalogVerificationReport;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationCatalog;

interface AuthorizationCatalogVerificationRepository
{
    public function verify(AuthorizationCatalog $catalog): AuthorizationCatalogVerificationReport;
}
