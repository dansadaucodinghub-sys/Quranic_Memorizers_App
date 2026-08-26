<?php

declare(strict_types=1);

namespace Qmdb\Shared\Observability\Logging;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

final readonly class LogContextSanitizer
{
    public const DEFAULT_MAXIMUM_DEPTH = 5;
    public const DEFAULT_MAXIMUM_ENTRIES = 50;
    public const DEFAULT_MAXIMUM_STRING_LENGTH = 2048;
    private const TRUNCATED = '[truncated]';

    public function __construct(
        private int $maximumDepth = self::DEFAULT_MAXIMUM_DEPTH,
        private int $maximumEntries = self::DEFAULT_MAXIMUM_ENTRIES,
        private int $maximumStringLength = self::DEFAULT_MAXIMUM_STRING_LENGTH,
    ) {
    }

    /** @param array<string, mixed> $context
     *  @return array<string, mixed>
     */
    public function sanitize(array $context): array
    {
        $sanitized = [];
        foreach ($this->sanitizeArray($context, 0) as $key => $value) {
            if (is_string($key)) {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /** @param array<array-key, mixed> $values
     *  @return array<array-key, mixed>
     */
    private function sanitizeArray(array $values, int $depth): array
    {
        if ($depth >= $this->maximumDepth) {
            return ['[maximum-depth]'];
        }

        $sanitized = [];
        $position = 0;
        foreach ($values as $key => $value) {
            if ($position >= $this->maximumEntries) {
                $sanitized['__truncated_entries'] = self::TRUNCATED;
                break;
            }

            $sanitized[$key] = $this->sanitizeValue($value, $depth);
            ++$position;
        }

        return $sanitized;
    }

    private function sanitizeValue(mixed $value, int $depth): mixed
    {
        if ($value === null || is_bool($value) || is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return is_finite($value) ? $value : '[non-finite-float]';
        }

        if (is_string($value)) {
            return $this->truncate($value);
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value)
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d\TH:i:s.uP');
        }

        if (is_array($value)) {
            return $this->sanitizeArray($value, $depth + 1);
        }

        if (is_resource($value)) {
            return '[resource]';
        }

        if (is_object($value)) {
            return $this->truncate('[object:' . $value::class . ']');
        }

        return '[unsupported]';
    }

    private function truncate(string $value): string
    {
        if (mb_strlen($value, 'UTF-8') <= $this->maximumStringLength) {
            return $value;
        }

        $markerLength = mb_strlen(self::TRUNCATED, 'UTF-8');

        return mb_substr($value, 0, $this->maximumStringLength - $markerLength, 'UTF-8') . self::TRUNCATED;
    }
}
