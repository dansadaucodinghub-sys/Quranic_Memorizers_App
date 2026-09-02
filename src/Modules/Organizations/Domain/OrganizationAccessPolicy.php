<?php

declare(strict_types=1);

namespace Qmdb\Modules\Organizations\Domain;

final readonly class OrganizationAccessPolicy
{
    /**
     * @param array<string, mixed>|null $organization
     */
    public function visible(?array $organization): OrganizationAccessDecision
    {
        return $organization !== null
            ? OrganizationAccessDecision::allow()
            : OrganizationAccessDecision::deny(OrganizationAccessReason::ORGANIZATION_UNAVAILABLE);
    }
}
