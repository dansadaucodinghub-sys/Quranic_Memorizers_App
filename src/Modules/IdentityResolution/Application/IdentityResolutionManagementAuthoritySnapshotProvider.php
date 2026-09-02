<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Application;

use Qmdb\Modules\IdentityResolution\Infrastructure\Persistence\MySqlIdentityResolutionRepository;
use Qmdb\Modules\People\Application\PersonManagementAuthoritySnapshotProvider;

final readonly class IdentityResolutionManagementAuthoritySnapshotProvider implements PersonManagementAuthoritySnapshotProvider
{
    public function __construct(private MySqlIdentityResolutionRepository $repository)
    {
    }

    public function authoritiesForPerson(int $personId): array
    {
        return $this->repository->managementAuthorities($personId);
    }
}
