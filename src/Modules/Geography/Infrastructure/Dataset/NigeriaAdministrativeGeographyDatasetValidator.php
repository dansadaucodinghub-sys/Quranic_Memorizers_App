<?php

declare(strict_types=1);

namespace Qmdb\Modules\Geography\Infrastructure\Dataset;

use Qmdb\Modules\Geography\Domain\AdministrativeAreaCode;
use Qmdb\Modules\Geography\Domain\AdministrativeAreaName;
use Qmdb\Modules\Geography\Domain\CanonicalAreaSlug;
use Qmdb\Modules\Geography\Domain\GeographyAreaType;
use Qmdb\Modules\Geography\Domain\GeographyPublicId;
use Throwable;

final class NigeriaAdministrativeGeographyDatasetValidator
{
    private const MAX_ERRORS = 32;

    public function validate(NigeriaAdministrativeGeographyDataset $dataset): GeographyDatasetValidationReport
    {
        $errors = $this->emptyErrors();
        try {
            $metadata = $dataset->metadata();
            $country = $dataset->country();
            $areas = $dataset->areas();
            if (
                array_keys($metadata) !== [
                'dataset_code', 'dataset_version', 'source_authority', 'source_title', 'source_reference',
                'source_published_on', 'source_retrieved_at', 'extraction_reference', 'cross_check_authority',
                'cross_check_reference', 'canonicalization', 'official_code_policy', 'project_code_policy',
                'counts', 'content_sha256', 'known_limitations',
                ]
            ) {
                $this->add($errors, 'Dataset metadata keys are invalid.');
            }
            foreach (
                ['dataset_code', 'dataset_version', 'source_authority', 'source_title', 'source_reference',
                'source_published_on', 'source_retrieved_at', 'extraction_reference', 'cross_check_authority',
                'cross_check_reference', 'canonicalization', 'official_code_policy', 'project_code_policy',
                'content_sha256', 'known_limitations'] as $key
            ) {
                if (!$this->validString($metadata[$key] ?? null, 512)) {
                    $this->add($errors, 'Dataset metadata field is invalid: ' . $key);
                }
            }
            if (
                !is_string($metadata['content_sha256'] ?? null)
                || !preg_match('/\A[a-f0-9]{64}\z/D', $metadata['content_sha256'])
            ) {
                $this->add($errors, 'Dataset content checksum is invalid.');
            } elseif (!hash_equals($metadata['content_sha256'], $dataset->contentChecksum())) {
                $this->add($errors, 'Dataset content checksum does not match canonical payload.');
            }
            if (
                ($country['iso_alpha2'] ?? null) !== 'NG' || ($country['iso_alpha3'] ?? null) !== 'NGA'
                || ($country['iso_numeric'] ?? null) !== '566' || ($country['status'] ?? null) !== 'ACTIVE'
                || ($country['version'] ?? null) !== 1
            ) {
                $this->add($errors, 'Nigeria country codes or status are invalid.');
            }
            $this->assertPublicId($country['public_id'] ?? null, 'country', $errors);
            foreach (['common_name', 'official_name', 'canonical_slug'] as $key) {
                if (!$this->validString($country[$key] ?? null, 191)) {
                    $this->add($errors, 'Country field is invalid: ' . $key);
                }
            }
            $counts = $metadata['counts'] ?? null;
            if (
                !is_array($counts) || $counts !== [
                'countries' => 1, 'level_one' => 37, 'states' => 36, 'federal_capital_territories' => 1,
                'level_two' => 774, 'local_government_areas' => 768, 'area_councils' => 6,
                'administrative_areas' => 811,
                ]
            ) {
                $this->add($errors, 'Dataset declared counts are invalid.');
            }
            $this->validateAreas($areas, $errors);
        } catch (Throwable) {
            $this->add($errors, 'Dataset has an invalid structural value.');
        }

        return new GeographyDatasetValidationReport($errors);
    }

    /**
     * @param list<array<string, mixed>> $areas
     * @param list<string> $errors
     */
    private function validateAreas(array $areas, array &$errors): void
    {
        if (count($areas) !== 811) {
            $this->add($errors, 'Administrative area count must be 811.');
        }
        $ids = [];
        $codes = [];
        $scopeSlugs = [];
        $areaByCode = [];
        $levels = [1 => 0, 2 => 0];
        $types = array_fill_keys(array_map(static fn (GeographyAreaType $type): string => $type->value, GeographyAreaType::cases()), 0);
        foreach ($areas as $area) {
            $this->assertPublicId($area['public_id'] ?? null, 'area', $errors);
            foreach (['public_id', 'canonical_code', 'canonical_slug', 'official_name', 'search_name'] as $key) {
                if (!array_key_exists($key, $area)) {
                    $this->add($errors, 'Administrative area field is missing: ' . $key);
                }
            }
            try {
                $id = (new GeographyPublicId($this->stringValue($area['public_id'] ?? null)))->value();
                $code = (new AdministrativeAreaCode($this->stringValue($area['canonical_code'] ?? null)))->value();
                $slug = (new CanonicalAreaSlug($this->stringValue($area['canonical_slug'] ?? null)))->value();
                new AdministrativeAreaName($this->stringValue($area['official_name'] ?? null));
                new AdministrativeAreaName($this->stringValue($area['search_name'] ?? null));
                if (isset($ids[$id])) {
                    $this->add($errors, 'Duplicate administrative area public identifier.');
                }
                if (isset($codes[$code])) {
                    $this->add($errors, 'Duplicate administrative area canonical code.');
                }
                $ids[$id] = true;
                $codes[$code] = true;
                $areaByCode[$code] = $area;
                $parent = $area['parent_canonical_code'] ?? null;
                $scope = (is_string($parent) ? $parent : 'ROOT') . '|' . $slug;
                if (isset($scopeSlugs[$scope])) {
                    $this->add($errors, 'Duplicate administrative area slug within parent scope.');
                }
                $scopeSlugs[$scope] = true;
            } catch (Throwable) {
                $this->add($errors, 'Administrative area identifier, code, slug, or name is invalid.');
            }
            $level = $area['administrative_level'] ?? null;
            $type = $area['area_type'] ?? null;
            if (!is_int($level) || !isset($levels[$level]) || !is_string($type) || !isset($types[$type])) {
                $this->add($errors, 'Administrative area type or level is invalid.');
                continue;
            }
            ++$levels[$level];
            ++$types[$type];
            if (GeographyAreaType::from($type)->level() !== $level) {
                $this->add($errors, 'Administrative area type and level do not match.');
            }
        }
        foreach ($areas as $area) {
            $level = $area['administrative_level'] ?? null;
            $parent = $area['parent_canonical_code'] ?? null;
            $type = $area['area_type'] ?? null;
            if ($level === 1 && $parent !== null) {
                $this->add($errors, 'Level-1 area cannot have a parent.');
            }
            if ($level !== 2) {
                continue;
            }
            if (!is_string($parent) || !isset($areaByCode[$parent])) {
                $this->add($errors, 'Level-2 area parent is missing or unknown.');
                continue;
            }
            $parentType = $areaByCode[$parent]['area_type'] ?? null;
            if ($type === GeographyAreaType::AREA_COUNCIL->value && $parentType !== GeographyAreaType::FEDERAL_CAPITAL_TERRITORY->value) {
                $this->add($errors, 'Area Council is not parented by the FCT.');
            }
            if ($type === GeographyAreaType::LOCAL_GOVERNMENT_AREA->value && $parentType !== GeographyAreaType::STATE->value) {
                $this->add($errors, 'LGA is not parented by a State.');
            }
        }
        if (
            $levels !== [1 => 37, 2 => 774] || $types !== [
            'STATE' => 36, 'FEDERAL_CAPITAL_TERRITORY' => 1, 'LOCAL_GOVERNMENT_AREA' => 768, 'AREA_COUNCIL' => 6,
            ]
        ) {
            $this->add($errors, 'Administrative area aggregate counts are invalid.');
        }
    }

    /** @param list<string> $errors */
    private function assertPublicId(mixed $value, string $subject, array &$errors): void
    {
        try {
            new GeographyPublicId(is_string($value) ? $value : '');
        } catch (Throwable) {
            $this->add($errors, 'Invalid public identifier for ' . $subject . '.');
        }
    }

    private function validString(mixed $value, int $maximum): bool
    {
        return is_string($value) && $value !== '' && strlen($value) <= $maximum && mb_check_encoding($value, 'UTF-8')
            && !str_contains($value, "\0") && preg_match('/[\x00-\x1F\x7F]/u', $value) !== 1;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    /** @return list<string> */
    private function emptyErrors(): array
    {
        return [];
    }

    /** @param list<string> $errors */
    private function add(array &$errors, string $message): void
    {
        if (count($errors) < self::MAX_ERRORS && !in_array($message, $errors, true)) {
            $errors[] = $message;
        }
    }
}
