<?php

declare(strict_types=1);

namespace Qmdb\Modules\OrganizationAffiliations\Application;

use Qmdb\Modules\OrganizationAffiliations\Configuration\OrganizationAffiliationsConfiguration;
use Qmdb\Modules\OrganizationAffiliations\Domain\OrganizationAffiliationTitle;

final readonly class OrganizationAffiliationInput
{
    /**
     * @param list<array{code: string, is_primary: bool, unit_public_id: ?string, title: ?string}> $roles
     * @param list<array{public_id: string, is_primary: bool}> $units
     */
    private function __construct(
        public array $roles,
        public array $units,
    ) {
    }

    /** @param array<string,mixed> $body */
    public static function fromBody(array $body, OrganizationAffiliationsConfiguration $configuration): self
    {
        $rawRoles = $body['roles'] ?? [];
        $rawUnits = $body['units'] ?? [];
        if (!is_array($rawRoles) || !is_array($rawUnits) || count($rawRoles) < 1 || count($rawRoles) > $configuration->maximumRoles || count($rawUnits) > $configuration->maximumUnits) {
            throw new \InvalidArgumentException('Affiliation assignment set is invalid.');
        }
        $roles = [];
        $primaries = 0;
        $keys = [];
        foreach ($rawRoles as $rawRole) {
            if (!is_array($rawRole) || !is_string($rawRole['code'] ?? null) || preg_match('/\A[A-Z][A-Z0-9_]{1,47}\z/', $rawRole['code']) !== 1 || !is_bool($rawRole['is_primary'] ?? null)) {
                throw new \InvalidArgumentException('Affiliation role assignment is invalid.');
            }
            $unit = $rawRole['unit_public_id'] ?? null;
            if ($unit !== null && (!is_string($unit) || !self::uuid($unit))) {
                throw new \InvalidArgumentException('Affiliation role unit scope is invalid.');
            }
            $title = OrganizationAffiliationTitle::optional(is_string($rawRole['title'] ?? null) ? $rawRole['title'] : null, $configuration->titleMaximumBytes)->value;
            $key = $rawRole['code'] . "\0" . ($unit ?? '');
            if (isset($keys[$key])) {
                throw new \InvalidArgumentException('Affiliation role assignment is duplicated.');
            }
            $keys[$key] = true;
            $primaries += $rawRole['is_primary'] ? 1 : 0;
            $roles[] = ['code' => $rawRole['code'],'is_primary' => $rawRole['is_primary'],'unit_public_id' => $unit,'title' => $title];
        }
        if ($primaries !== 1) {
            throw new \InvalidArgumentException('Exactly one affiliation role must be primary.');
        }
        $units = [];
        $unitIds = [];
        $unitPrimaries = 0;
        foreach ($rawUnits as $rawUnit) {
            if (!is_array($rawUnit) || !is_string($rawUnit['public_id'] ?? null) || !self::uuid($rawUnit['public_id']) || !is_bool($rawUnit['is_primary'] ?? null) || isset($unitIds[$rawUnit['public_id']])) {
                throw new \InvalidArgumentException('Affiliation unit assignment is invalid.');
            }
            $unitIds[$rawUnit['public_id']] = true;
            $unitPrimaries += $rawUnit['is_primary'] ? 1 : 0;
            $units[] = ['public_id' => $rawUnit['public_id'],'is_primary' => $rawUnit['is_primary']];
        }
        if ($unitPrimaries > 1) {
            throw new \InvalidArgumentException('At most one affiliation unit may be primary.');
        }
        foreach ($roles as $role) {
            if ($role['unit_public_id'] !== null && !isset($unitIds[$role['unit_public_id']])) {
                throw new \InvalidArgumentException('Role unit scope must be selected in the assignment set.');
            }
        }

        return new self($roles, $units);
    }

    private static function uuid(string $value): bool
    {
        return preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[78][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i', $value) === 1;
    }
}
