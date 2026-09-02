<?php

declare(strict_types=1);

namespace Qmdb\Modules\Organizations\Application;

use Qmdb\Modules\Organizations\Configuration\OrganizationsRegistryConfiguration;

/** Validated private input; classifications describe an Organization and never confer authority. */
final readonly class OrganizationInput
{
    /** @param list<string> $classificationCodes */
    public function __construct(public string $primaryName, public string $primaryScript, public array $classificationCodes, public string $primaryClassification, public string $jurisdictionLevel, public ?string $countryPublicId, public ?string $levelOnePublicId, public ?string $levelTwoPublicId, public string $unitName, public string $unitScript, public string $unitType, public ?string $unitCountryPublicId, public ?string $unitLevelOnePublicId, public ?string $unitLevelTwoPublicId)
    {
    }
    /** @param array<string,mixed> $body */
    public static function fromBody(array $body, OrganizationsRegistryConfiguration $config): self
    {
        $name = self::text($body, 'primary_name', $config->nameMaximumBytes);
        $script = self::enum($body, 'primary_script', ['LATIN','ARABIC','OTHER'], 'LATIN');
        $raw = $body['classification_codes'] ?? [];
        if (is_string($raw)) {
            $raw = preg_split('/\s*,\s*/', $raw) ?: [];
        } if (!is_array($raw) || $raw === [] || count($raw) > $config->maximumClassifications) {
            throw new \InvalidArgumentException('Organization classifications are invalid.');
        }
        $codes = [];
        foreach ($raw as $code) {
            if (!is_string($code) || preg_match('/\A[A-Z][A-Z0-9_]{1,47}\z/', $code) !== 1) {
                throw new \InvalidArgumentException('Organization classification is invalid.');
            }$codes[] = $code;
        } $codes = array_values(array_unique($codes));
        $primary = self::enum($body, 'primary_classification', $codes, $codes[0]);
        $level = self::enum($body, 'jurisdiction_level', ['NOT_RECORDED','COUNTRY','LEVEL_1','LEVEL_2'], 'NOT_RECORDED');
        $country = self::uuid($body, 'jurisdiction_country_id');
        $one = self::uuid($body, 'jurisdiction_level_one_id');
        $two = self::uuid($body, 'jurisdiction_level_two_id');
        self::shape($level, $country, $one, $two, 'Jurisdiction');
        $unitName = self::text($body, 'unit_name', $config->nameMaximumBytes);
        $unitScript = self::enum($body, 'unit_script', ['LATIN','ARABIC','OTHER'], 'LATIN');
        $unitType = self::enum($body, 'unit_type', ['HEADQUARTERS','BRANCH','CAMPUS','CENTRE','SCHOOL','MOSQUE','OFFICE','OTHER'], 'HEADQUARTERS');
        $unitCountry = self::uuid($body, 'unit_country_id');
        $unitOne = self::uuid($body, 'unit_level_one_id');
        $unitTwo = self::uuid($body, 'unit_level_two_id');
        if ($unitCountry === null && ($unitOne !== null || $unitTwo !== null)) {
            throw new \InvalidArgumentException('Unit location is invalid.');
        }if ($unitTwo !== null && $unitOne === null) {
            throw new \InvalidArgumentException('Unit location is invalid.');
        }
        return new self($name, $script, $codes, $primary, $level, $country, $one, $two, $unitName, $unitScript, $unitType, $unitCountry, $unitOne, $unitTwo);
    }
    /** @param array<string, mixed> $body */
    private static function text(array $body, string $field, int $max): string
    {
        $value = $body[$field] ?? null;
        if (!is_string($value)) {
            throw new \InvalidArgumentException($field . ' is invalid.');
        }$value = preg_replace('/\s+/u', ' ', trim($value));
        if (!is_string($value) || $value === '' || strlen($value) > $max || preg_match('/[\x00-\x1F\x7F]/u', $value) === 1 || strip_tags($value) !== $value) {
            throw new \InvalidArgumentException($field . ' is invalid.');
        }return $value;
    }
    /**
     * @param array<string, mixed> $body
     * @param list<string> $allowed
     */
    private static function enum(array $body, string $field, array $allowed, string $default): string
    {
        $value = $body[$field] ?? $default;
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            throw new \InvalidArgumentException($field . ' is invalid.');
        }return $value;
    }
    /** @param array<string, mixed> $body */
    private static function uuid(array $body, string $field): ?string
    {
        $value = $body[$field] ?? null;
        if ($value === null || $value === '') {
            return null;
        }if (!is_string($value) || preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i', $value) !== 1) {
            throw new \InvalidArgumentException($field . ' is invalid.');
        }return strtolower($value);
    }
    private static function shape(string $level, ?string $country, ?string $one, ?string $two, string $label): void
    {
        if (($level === 'NOT_RECORDED' && ($country !== null || $one !== null || $two !== null)) || ($level === 'COUNTRY' && ($country === null || $one !== null || $two !== null)) || ($level === 'LEVEL_1' && ($country === null || $one === null || $two !== null)) || ($level === 'LEVEL_2' && ($country === null || $one === null || $two === null))) {
            throw new \InvalidArgumentException($label . ' is invalid.');
        }
    }
}
