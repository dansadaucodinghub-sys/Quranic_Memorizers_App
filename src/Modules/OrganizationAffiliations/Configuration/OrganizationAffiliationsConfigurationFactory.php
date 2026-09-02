<?php

declare(strict_types=1);

namespace Qmdb\Modules\OrganizationAffiliations\Configuration;

use Qmdb\Shared\Configuration\EnvironmentVariables;

final readonly class OrganizationAffiliationsConfigurationFactory
{
    public function create(EnvironmentVariables $variables): OrganizationAffiliationsConfiguration
    {
        return new OrganizationAffiliationsConfiguration(
            $this->integer($variables, 'ORGANIZATION_AFFILIATION_REQUEST_TTL_SECONDS', 2_592_000, 300, 31_536_000),
            $this->integer($variables, 'ORGANIZATION_AFFILIATION_CODE_ENTROPY_BITS', 80, 80, 256),
            $this->integer($variables, 'ORGANIZATION_AFFILIATION_TITLE_MAX_BYTES', 160, 1, 160),
            $this->integer($variables, 'ORGANIZATION_AFFILIATION_MAX_ROLES', 12, 1, 24),
            $this->integer($variables, 'ORGANIZATION_AFFILIATION_MAX_UNITS', 50, 1, 100),
            $this->integer($variables, 'ORGANIZATION_AFFILIATION_MAX_PAGE_SIZE', 100, 1, 100),
            $this->integer($variables, 'ORGANIZATION_AFFILIATION_MAINTENANCE_BATCH_SIZE', 100, 1, 500),
            $this->integer($variables, 'ORGANIZATION_AFFILIATION_REQUEST_WINDOW_SECONDS', 900, 60, 86_400),
            $this->integer($variables, 'ORGANIZATION_AFFILIATION_REQUEST_MAX_ATTEMPTS', 30, 1, 500),
            $this->integer($variables, 'ORGANIZATION_AFFILIATION_RESPONSE_WINDOW_SECONDS', 900, 60, 86_400),
            $this->integer($variables, 'ORGANIZATION_AFFILIATION_RESPONSE_MAX_ATTEMPTS', 30, 1, 500),
            $this->integer($variables, 'ORGANIZATION_AFFILIATION_MUTATION_WINDOW_SECONDS', 900, 60, 86_400),
            $this->integer($variables, 'ORGANIZATION_AFFILIATION_MUTATION_MAX_ATTEMPTS', 60, 1, 500),
        );
    }

    private function integer(EnvironmentVariables $variables, string $name, int $default, int $minimum, int $maximum): int
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
