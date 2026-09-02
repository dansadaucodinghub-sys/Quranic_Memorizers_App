<?php

declare(strict_types=1);

namespace Qmdb\Modules\OrganizationAffiliations\Domain;

final readonly class OrganizationAffiliationRoleDefinition
{
    public function __construct(
        public string $publicId,
        public string $code,
        public string $category,
        public string $sensitivityLevel,
        public ?string $requiredPersonRoleType,
        public string $status,
        public int $sortOrder,
    ) {
        if (preg_match('/\A[A-Z][A-Z0-9_]{1,47}\z/', $code) !== 1) {
            throw new \InvalidArgumentException('Affiliation role code is invalid.');
        }
        if (!in_array($category, ['MEMBERSHIP', 'STUDY', 'QURAN_PARTICIPATION', 'TEACHING', 'RELIGIOUS_SERVICE', 'STAFF', 'VOLUNTEERING', 'LEADERSHIP', 'REPRESENTATION'], true)) {
            throw new \InvalidArgumentException('Affiliation role category is invalid.');
        }
        if (!in_array($sensitivityLevel, ['NORMAL', 'LEADERSHIP'], true) || !in_array($status, ['ACTIVE', 'RETIRED'], true)) {
            throw new \InvalidArgumentException('Affiliation role definition is invalid.');
        }
        if ($requiredPersonRoleType !== null && !in_array($requiredPersonRoleType, ['MEMORIZER', 'RECITER'], true)) {
            throw new \InvalidArgumentException('Affiliation role compatibility is invalid.');
        }
    }

    public function isLeadershipSensitive(): bool
    {
        return $this->sensitivityLevel === 'LEADERSHIP';
    }
}
