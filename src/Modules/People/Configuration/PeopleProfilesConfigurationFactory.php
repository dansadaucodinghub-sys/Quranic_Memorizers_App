<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Configuration;

use Qmdb\Shared\Configuration\EnvironmentVariables;

final readonly class PeopleProfilesConfigurationFactory
{
    public function create(EnvironmentVariables $variables): PeopleProfilesConfiguration
    {
        return new PeopleProfilesConfiguration(
            $this->bounded($variables, 'PERSON_MINOR_THRESHOLD_YEARS', 18, 1, 25),
            $this->bounded($variables, 'PERSON_MAX_AGE_YEARS', 120, 100, 130),
            $this->bounded($variables, 'PERSON_PROFILE_NAME_MAX_BYTES', 200, 32, 200),
            $this->bounded($variables, 'PERSON_PROFILE_SEARCH_NAME_MAX_BYTES', 256, 32, 256),
            $this->bounded($variables, 'PERSON_PROFILE_MAX_DEPENDENTS_PER_GUARDIAN', 50, 1, 100),
            $this->bounded($variables, 'PERSON_PROFILE_MUTATION_WINDOW_SECONDS', 900, 60, 86400),
            $this->bounded($variables, 'PERSON_PROFILE_MUTATION_MAX_ATTEMPTS', 30, 1, 100),
            $this->bounded($variables, 'DEPENDENT_PROFILE_CREATION_WINDOW_SECONDS', 3600, 60, 86400),
            $this->bounded($variables, 'DEPENDENT_PROFILE_CREATION_MAX_ATTEMPTS', 10, 1, 100),
        );
    }

    private function bounded(EnvironmentVariables $variables, string $name, int $default, int $minimum, int $maximum): int
    {
        $value = $variables->optionalString($name);
        if ($value === null) {
            return $default;
        }
        if (preg_match('/\A[1-9][0-9]*\z/', $value) !== 1 || (int) $value < $minimum || (int) $value > $maximum) {
            throw new \InvalidArgumentException($name . ' is outside its approved range.');
        }

        return (int) $value;
    }
}
