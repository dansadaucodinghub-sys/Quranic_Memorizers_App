<?php

declare(strict_types=1);

namespace Qmdb\Modules\Geography\Infrastructure\Dataset;

final readonly class GeographyDatasetValidationReport
{
    /** @param list<string> $errors */
    public function __construct(private array $errors)
    {
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /** @return list<string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function failureSummary(): string
    {
        return implode('; ', array_slice($this->errors, 0, 8));
    }
}
