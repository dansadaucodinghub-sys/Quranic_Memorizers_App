<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Application;

use Qmdb\Modules\IdentityResolution\Infrastructure\Persistence\MySqlIdentityResolutionRepository;
use Qmdb\Modules\People\Application\PersonCanonicalIdentityResolver;
use Qmdb\Modules\People\Application\PersonVerificationProjectionProvider;

final readonly class IdentityResolutionVerificationProjectionProvider implements PersonVerificationProjectionProvider
{
    public function __construct(private MySqlIdentityResolutionRepository $repository, private PersonCanonicalIdentityResolver $resolver)
    {
    }

    public function activeAssertionTypes(int $personId): array
    {
        $group = $this->resolver->group($personId);
        $ids = array_merge([$group->canonicalPersonId], $group->sourcePersonIds);

        return $this->repository->activeAssertionTypesForPersonIds($ids);
    }
}
