<?php

declare(strict_types=1);

namespace Qmdb\Shared\Observability\Logging;

final readonly class SensitiveValueRedactor
{
    public const REDACTED = '[REDACTED]';

    public function __construct(
        private SensitiveKeyMatcher $keyMatcher,
        private int $maximumDepth = 5,
    ) {
    }

    /** @param array<string, mixed> $context
     *  @return array<string, mixed>
     */
    public function redact(array $context): array
    {
        $redacted = [];
        foreach ($this->redactArray($context, 0) as $key => $value) {
            if (is_string($key)) {
                $redacted[$key] = $value;
            }
        }

        return $redacted;
    }

    /** @param array<array-key, mixed> $values
     *  @return array<array-key, mixed>
     */
    private function redactArray(array $values, int $depth): array
    {
        if ($depth >= $this->maximumDepth) {
            return ['[maximum-depth]'];
        }

        $redacted = [];
        foreach ($values as $key => $value) {
            if (is_string($key) && $this->keyMatcher->matches($key)) {
                $redacted[$key] = self::REDACTED;
                continue;
            }

            $redacted[$key] = is_array($value)
                ? $this->redactArray($value, $depth + 1)
                : $value;
        }

        return $redacted;
    }
}
