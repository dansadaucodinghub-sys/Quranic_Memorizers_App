<?php

declare(strict_types=1);

namespace Qmdb\Modules\Organizations\Configuration;

use Qmdb\Shared\Configuration\EnvironmentVariables;

final readonly class OrganizationsRegistryConfigurationFactory
{
    public function create(EnvironmentVariables $v): OrganizationsRegistryConfiguration
    {
        return new OrganizationsRegistryConfiguration($this->integer($v, 'ORGANIZATION_UNIT_MAX_DEPTH', 8, 1, 8), $this->integer($v, 'ORGANIZATION_NAME_MAX_BYTES', 240, 32, 240), $this->integer($v, 'ORGANIZATION_SEARCH_NAME_MAX_BYTES', 320, 32, 320), $this->integer($v, 'ORGANIZATION_MAX_CLASSIFICATIONS', 10, 1, 10), $this->integer($v, 'ORGANIZATION_MAX_UNITS', 5000, 1, 5000), $this->integer($v, 'ORGANIZATION_MUTATION_WINDOW_SECONDS', 900, 60, 86400), $this->integer($v, 'ORGANIZATION_MUTATION_MAX_ATTEMPTS', 60, 1, 500), $this->integer($v, 'ORGANIZATION_UNIT_MUTATION_WINDOW_SECONDS', 900, 60, 86400), $this->integer($v, 'ORGANIZATION_UNIT_MUTATION_MAX_ATTEMPTS', 120, 1, 500));
    }
    private function integer(EnvironmentVariables $v, string $name, int $default, int $min, int $max): int
    {
        $value = $v->optionalString($name);
        if ($value === null) {
            return $default;
        } if (preg_match('/\A[1-9][0-9]*\z/', $value) !== 1 || (int)$value < $min || (int)$value > $max) {
            throw new \InvalidArgumentException($name . ' is outside its approved range.');
        } return (int)$value;
    }
}
