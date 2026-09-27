<?php

declare(strict_types=1);

namespace Qmdb\Modules\ProductionHardening\Domain;

final readonly class OperationalLogRedactor
{
    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function redact(array $context): array
    {
        $redacted = [];
        foreach ($context as $key => $value) {
            if (preg_match('/secret|token|password|credential|authorization|cookie|payload|body/i', $key) === 1) {
                $redacted[$key] = '[REDACTED]';
                continue;
            }
            $redacted[$key] = $this->redactValue($value);
        }
        return $redacted;
    }

    private function redactValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        $redacted = [];
        foreach ($value as $key => $nested) {
            if (is_string($key) && preg_match('/secret|token|password|credential|authorization|cookie|payload|body/i', $key) === 1) {
                $redacted[$key] = '[REDACTED]';
                continue;
            }
            $redacted[$key] = $this->redactValue($nested);
        }
        return $redacted;
    }
}
