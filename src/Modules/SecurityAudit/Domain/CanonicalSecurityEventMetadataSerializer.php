<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Domain;

use InvalidArgumentException;

final readonly class CanonicalSecurityEventMetadataSerializer
{
    /** @var array<string, true> */
    private const array ALLOWED_KEYS = [
        'access_type' => true, 'activation_public_id' => true, 'assignment_public_id' => true,
        'assurance_level' => true, 'authenticator_public_id' => true, 'new_status' => true,
        'operation_public_id' => true, 'permission_code' => true, 'previous_status' => true,
        'reason_code' => true, 'role_code' => true, 'scope_type' => true,
        'target_account_version_after' => true, 'target_account_version_before' => true,
    ];

    public function __construct(private int $maximumBytes)
    {
        if ($maximumBytes < 1 || $maximumBytes > 4096) {
            throw new InvalidArgumentException('Security audit metadata size limit is invalid.');
        }
    }

    /** @param array<string, mixed> $metadata */
    public function serialize(array $metadata): string
    {
        $normalised = $this->normaliseMap($metadata, 0);
        try {
            $json = json_encode($normalised, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (\JsonException $exception) {
            throw new InvalidArgumentException('Security audit metadata cannot be canonicalized.', 0, $exception);
        }
        if (strlen($json) > $this->maximumBytes) {
            throw new InvalidArgumentException('Security audit metadata exceeds its configured limit.');
        }

        return $json;
    }

    /**
     * @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    private function normaliseMap(array $value, int $depth): array
    {
        if ($depth > 3 || count($value) > 32) {
            throw new InvalidArgumentException('Security audit metadata structure is too large.');
        }
        $result = [];
        foreach ($value as $key => $item) {
            if (!isset(self::ALLOWED_KEYS[$key])) {
                throw new InvalidArgumentException('Security audit metadata key is not approved.');
            }
            $result[$key] = $this->normaliseValue($item, $depth + 1);
        }
        ksort($result, SORT_STRING);

        return $result;
    }

    private function normaliseValue(mixed $value, int $depth): mixed
    {
        if (is_float($value) || is_object($value) || is_resource($value)) {
            throw new InvalidArgumentException('Security audit metadata value type is not approved.');
        }
        if (is_string($value)) {
            if (!mb_check_encoding($value, 'UTF-8') || preg_match('/[\\x00-\\x1F\\x7F]/u', $value) === 1) {
                throw new InvalidArgumentException('Security audit metadata string is invalid.');
            }
            if (strlen($value) > 256) {
                throw new InvalidArgumentException('Security audit metadata string is too long.');
            }

            return $value;
        }
        if (is_int($value) || is_bool($value) || $value === null) {
            return $value;
        }
        if (!is_array($value) || $depth > 3 || count($value) > 16) {
            throw new InvalidArgumentException('Security audit metadata value is invalid.');
        }
        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->normaliseValue($item, $depth + 1), $value);
        }

        return $this->normaliseMap($this->stringKeyedMap($value), $depth + 1);
    }

    /**
     * @param array<array-key, mixed> $value
     * @return array<string, mixed>
     */
    private function stringKeyedMap(array $value): array
    {
        $result = [];
        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                throw new InvalidArgumentException('Security audit metadata object key is invalid.');
            }
            $result[$key] = $item;
        }

        return $result;
    }
}
