<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Security;

use Qmdb\Modules\Geography\Application\GeographyReferenceReadinessCheck;
use Qmdb\Modules\IdentityResolution\Application\PeopleIdentityResolutionReadinessCheck;
use Qmdb\Modules\OrganizationAffiliations\Application\OrganizationAffiliationsReadinessCheck;
use Qmdb\Modules\Organizations\Application\OrganizationsRegistryReadinessCheck;
use Qmdb\Modules\People\Application\PeopleProfilesReadinessCheck;

/** Bounded, read-only composition of P3 People, Geography and Organization controls. */
final readonly class P3SecurityHardeningVerifier
{
    public function __construct(
        private P2SecurityHardeningVerifier $p2,
        private GeographyReferenceReadinessCheck $geography,
        private PeopleProfilesReadinessCheck $people,
        private OrganizationsRegistryReadinessCheck $organizations,
        private OrganizationAffiliationsReadinessCheck $affiliations,
        private PeopleIdentityResolutionReadinessCheck $identityResolution,
        private P3PersonRepositorySecurityVerifier $personRepositories,
    ) {
    }

    public function verify(): P3SecurityHardeningVerificationReport
    {
        $p2 = $this->p2->verify();
        $personRepositories = $this->personRepositories->verify();
        $components = [
            'p2_security_boundary' => $p2->isValid(),
            'geography_reference' => $this->geography->isReady(),
            'people_profiles' => $this->people->isReady(),
            'organizations_registry' => $this->organizations->isReady(),
            'organization_affiliations' => $this->affiliations->isReady(),
            'identity_resolution' => $this->identityResolution->isReady(),
            'person_repository_scopes' => $personRepositories->isValid(),
        ];
        $errors = [];
        foreach ($components as $name => $valid) {
            if (!$valid) {
                $errors[] = 'P3_SECURITY_COMPONENT_INVALID:' . $name;
            }
        }
        foreach ($personRepositories->errors as $error) {
            $errors[] = $error;
        }

        return new P3SecurityHardeningVerificationReport($components, $errors);
    }
}
