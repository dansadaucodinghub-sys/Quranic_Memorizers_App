<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Application;

use DateTimeImmutable;
use Qmdb\Modules\People\Configuration\PeopleProfilesConfiguration;
use Qmdb\Modules\People\Domain\PersonBirthDate;
use Qmdb\Modules\People\Domain\PersonName;
use Qmdb\Modules\People\Domain\PersonSexClassification;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class PersonProfileInput
{
    public function __construct(
        public PersonName $name,
        public string $searchName,
        public ?string $preferredName,
        public ?string $arabicName,
        public ?PersonBirthDate $birthDate,
        public PersonSexClassification $sex,
        public ?string $nationalityCountryPublicId,
        public ?string $originLevelOneAreaPublicId,
        public ?string $originLevelTwoAreaPublicId,
        public ?string $residenceLevelOneAreaPublicId,
        public ?string $residenceLevelTwoAreaPublicId,
        /** @var list<string> */
        public array $selectedRoleTypes,
        /** @var array{memorized_juz_count: int, progress_status: string, completed_on: string|null}|null */
        public ?array $memorizerProgress,
        public string $guardianshipRelationshipType,
    ) {
    }

    /** @param array<string, mixed> $body */
    public static function fromBody(array $body, PeopleProfilesConfiguration $configuration, DateTimeImmutable $now): self
    {
        $string = static function (mixed $value, string $field): ?string {
            if ($value === null || $value === '') {
                return null;
            }
            if (!is_string($value)) {
                throw new \InvalidArgumentException($field . ' is invalid.');
            }

            return $value;
        };
        $publicId = static function (mixed $value, string $field): ?string {
            if ($value === null || $value === '') {
                return null;
            }
            if (!is_string($value)) {
                throw new \InvalidArgumentException($field . ' is invalid.');
            }
            try {
                UuidV7::fromString($value);
            } catch (\Throwable) {
                throw new \InvalidArgumentException($field . ' is invalid.');
            }

            return $value;
        };
        $displayName = $string($body['display_name'] ?? null, 'display name');
        if ($displayName === null) {
            throw new \InvalidArgumentException('Display name is required.');
        }
        $sex = $string($body['sex_classification'] ?? null, 'sex classification') ?? 'NOT_RECORDED';
        $birth = $string($body['birth_date'] ?? null, 'birth date');

        $name = new PersonName([
                'given_name' => $string($body['given_name'] ?? null, 'given name'),
                'middle_names' => $string($body['middle_names'] ?? null, 'middle names'),
                'family_name' => $string($body['family_name'] ?? null, 'family name'),
                'display_name' => $displayName,
            ], $configuration->nameMaximumBytes);
        $preferred = $string($body['preferred_name'] ?? null, 'preferred name');
        $arabic = $string($body['arabic_name'] ?? null, 'Arabic name');
        $preferred = $preferred === null ? null : (new PersonName(['display_name' => $preferred], $configuration->nameMaximumBytes))->displayName();
        $arabic = $arabic === null ? null : (new PersonName(['display_name' => $arabic], $configuration->nameMaximumBytes))->displayName();
        $roles = $body['role_types'] ?? [];
        if (!is_array($roles)) {
            throw new \InvalidArgumentException('Person role selections are invalid.');
        }
        $selectedRoles = [];
        foreach ($roles as $role) {
            if (!is_string($role) || !in_array($role, ['MEMORIZER', 'RECITER', 'COMPETITOR', 'GUARDIAN'], true)) {
                throw new \InvalidArgumentException('Person role selections are invalid.');
            }
            $selectedRoles[$role] = $role;
        }
        $progressStatus = $string($body['progress_status'] ?? null, 'progress status');
        $progressJuzRaw = $body['memorized_juz_count'] ?? null;
        if ($progressJuzRaw !== null && $progressJuzRaw !== '' && (!is_string($progressJuzRaw) && !is_int($progressJuzRaw) || preg_match('/\A(?:0|[1-9][0-9]*)\z/', (string) $progressJuzRaw) !== 1)) {
            throw new \InvalidArgumentException('Memorized Juz count is invalid.');
        }
        $progressJuz = $progressJuzRaw === null || $progressJuzRaw === '' ? null : (int) $progressJuzRaw;
        $completedOn = $string($body['completed_on'] ?? null, 'completion date');
        $progress = null;
        if ($progressStatus !== null || $progressJuz !== null || $completedOn !== null) {
            if (!isset($selectedRoles['MEMORIZER']) || $progressJuz === null || !in_array($progressStatus, ['NOT_RECORDED', 'IN_PROGRESS', 'COMPLETE', 'MAINTENANCE'], true)) {
                throw new \InvalidArgumentException('Memorizer progress requires the Memorizer role.');
            }
            if ($progressJuz > 30 || (in_array($progressStatus, ['COMPLETE', 'MAINTENANCE'], true) && $progressJuz !== 30)) {
                throw new \InvalidArgumentException('Memorizer progress is invalid.');
            }
            if ($completedOn !== null && (!self::isIsoDate($completedOn) || $completedOn > $now->format('Y-m-d') || !in_array($progressStatus, ['COMPLETE', 'MAINTENANCE'], true))) {
                throw new \InvalidArgumentException('Memorizer completion date is invalid.');
            }
            $progress = ['memorized_juz_count' => $progressJuz, 'progress_status' => $progressStatus, 'completed_on' => $completedOn];
        }

        $relationshipType = $string($body['relationship_type'] ?? null, 'relationship type') ?? 'OTHER';
        if (!in_array($relationshipType, ['PARENT', 'LEGAL_GUARDIAN', 'CAREGIVER', 'OTHER'], true)) {
            throw new \InvalidArgumentException('Relationship type is invalid.');
        }

        return new self(
            $name,
            $name->searchName($configuration->searchNameMaximumBytes),
            $preferred,
            $arabic,
            $birth === null ? null : PersonBirthDate::fromString($birth, $now, $configuration->maximumAgeYears),
            PersonSexClassification::from($sex),
            $publicId($body['nationality_country_public_id'] ?? null, 'nationality country'),
            $publicId($body['origin_level_one_area_public_id'] ?? null, 'origin level one area'),
            $publicId($body['origin_level_two_area_public_id'] ?? null, 'origin level two area'),
            $publicId($body['residence_level_one_area_public_id'] ?? null, 'residence level one area'),
            $publicId($body['residence_level_two_area_public_id'] ?? null, 'residence level two area'),
            array_values($selectedRoles),
            $progress,
            $relationshipType,
        );
    }

    private static function isIsoDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value;
    }
}
