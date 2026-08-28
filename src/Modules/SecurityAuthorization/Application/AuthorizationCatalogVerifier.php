<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Application;

use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationCatalog;
use Qmdb\Modules\SecurityAuthorization\Domain\Repository\AuthorizationCatalogVerificationRepository;
use Qmdb\Shared\Configuration\Logging\LogLevel;
use Qmdb\Shared\Observability\Logging\EventLogger;
use Qmdb\Shared\Observability\Logging\LogEventName;

final readonly class AuthorizationCatalogVerifier
{
    public function __construct(
        private AuthorizationCatalog $catalog,
        private AuthorizationCatalogVerificationRepository $repository,
        private EventLogger $logger,
    ) {
    }

    public function verify(): AuthorizationCatalogVerificationReport
    {
        $errors = [];
        $database = $this->repository->verify($this->catalog);

        $report = new AuthorizationCatalogVerificationReport(
            $database->permissionCount,
            $database->roleCount,
            $database->mappingCount,
            $database->platformAssignmentCount,
            $database->workspaceAssignmentCount,
            [...$errors, ...$database->errors],
        );
        $this->logger->log(
            $report->isValid() ? LogLevel::INFO : LogLevel::ERROR,
            new LogEventName($report->isValid()
                ? 'authorization.catalog.verified'
                : 'authorization.catalog.invalid'),
            [
                'permission_count' => $report->permissionCount,
                'role_count' => $report->roleCount,
                'mapping_count' => $report->mappingCount,
                'error_codes' => $report->errors,
            ],
        );

        return $report;
    }
}
