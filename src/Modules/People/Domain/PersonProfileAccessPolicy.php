<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Domain;

/**
 * Evaluates only the Person-side relationships. Account and session activity are
 * established by AuthenticatedRequestGuard before this policy is invoked.
 */
final class PersonProfileAccessPolicy
{
    /** @param array<string, int|string>|null $person */
    public function self(?array $person): PersonProfileAccessDecision
    {
        if ($person === null) {
            return PersonProfileAccessDecision::deny(PersonProfileAccessReason::SELF_LINK_REQUIRED);
        }

        return $person['status'] === 'ACTIVE'
            ? PersonProfileAccessDecision::allow()
            : PersonProfileAccessDecision::deny(PersonProfileAccessReason::PERSON_UNAVAILABLE);
    }

    /** @param array<string, int|string>|null $guardian
     * @param array<string, int|string>|null $guardianRole
     * @param array<string, int|string>|null $dependent
     */
    public function guardian(?array $guardian, ?array $guardianRole, ?array $dependent): PersonProfileAccessDecision
    {
        $self = $this->self($guardian);
        if (!$self->allowed) {
            return $self;
        }
        if ($guardianRole === null || $guardianRole['status'] !== 'ACTIVE') {
            return PersonProfileAccessDecision::deny(PersonProfileAccessReason::GUARDIAN_ROLE_REQUIRED);
        }
        if ($dependent === null || $dependent['status'] !== 'ACTIVE') {
            return PersonProfileAccessDecision::deny(PersonProfileAccessReason::GUARDIANSHIP_REQUIRED);
        }

        return PersonProfileAccessDecision::allow();
    }
}
