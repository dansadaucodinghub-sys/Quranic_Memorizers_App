<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Application;

use Qmdb\Modules\IdentityResolution\Infrastructure\Persistence\MySqlIdentityResolutionRepository;
use Qmdb\Modules\OrganizationAffiliations\Application\OrganizationAffiliationPersonCanonicalizationParticipant;
use Qmdb\Modules\People\Application\PersonCanonicalizationParticipant;

final readonly class PersonDuplicateResolutionPreflight
{
    public function __construct(private MySqlIdentityResolutionRepository $repository, private PersonCanonicalizationParticipant $people, private OrganizationAffiliationPersonCanonicalizationParticipant $affiliations)
    {
    }

    public function plan(int $sourcePersonId, int $canonicalPersonId, int $maximumAffectedAffiliations): PersonDuplicateResolutionPlan
    {
        $source = $this->repository->personById($sourcePersonId);
        $canonical = $this->repository->personById($canonicalPersonId);
        if ($source === null || $canonical === null || $source['status'] !== 'ACTIVE' || $canonical['status'] !== 'ACTIVE' || $sourcePersonId === $canonicalPersonId) {
            return new PersonDuplicateResolutionPlan(['ALIAS_CONFLICT'], 0);
        }
        $conflicts = [];
        if ($this->repository->aliasForSource($sourcePersonId) !== null || $this->repository->aliasForSource($canonicalPersonId) !== null || $this->repository->inboundAliasCount($sourcePersonId) > 0) {
            $conflicts[] = 'ALIAS_CONFLICT';
        }
        $sourceLink = $this->repository->activeSelfLinkForPerson($sourcePersonId);
        $canonicalLink = $this->repository->activeSelfLinkForPerson($canonicalPersonId);
        if ($sourceLink !== null && $canonicalLink !== null) {
            $conflicts[] = 'IDENTITY_LINK_CONFLICT';
        }
        if ($sourceLink !== null && $canonicalLink === null) {
            $conflicts[] = 'CANONICAL_TARGET_SELF_LINK_REQUIRED';
        }
        foreach (['birth_date', 'sex_classification', 'nationality_country_id'] as $field) {
            $left = (string) ($source[$field] ?? '');
            $right = (string) ($canonical[$field] ?? '');
            if ($field === 'sex_classification' && ($left === 'NOT_RECORDED' || $right === 'NOT_RECORDED')) {
                continue;
            }
            if ($left !== '' && $right !== '' && $left !== $right) {
                $conflicts[] = 'DEMOGRAPHIC_CONFLICT';
                break;
            }
        }
        if ($this->repository->geographyConflict($sourcePersonId, $canonicalPersonId)) {
            $conflicts[] = 'GEOGRAPHY_CONFLICT';
        }
        $people = $this->people->preflight($sourcePersonId, $canonicalPersonId, $maximumAffectedAffiliations);
        $affiliations = $this->affiliations->preflight($sourcePersonId, $canonicalPersonId, $maximumAffectedAffiliations);
        $conflicts = array_merge($conflicts, $people->conflictCodes, $affiliations->conflictCodes);

        return new PersonDuplicateResolutionPlan(array_values(array_unique($conflicts)), $people->affectedRecordCount + $affiliations->affectedRecordCount);
    }
}
