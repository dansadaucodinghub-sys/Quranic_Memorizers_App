<?php

declare(strict_types=1);

namespace Qmdb\Modules\RecordPassport\Application;

/** Produces the intentionally narrow public projection for an active share. */
final class RecordPassportProjectionPolicy
{
    /** @param array<string,mixed> $entry
     * @return array<string,string|int|bool|null>
     */
    public function publicProjection(array $entry): array
    {
        $allowed = ['source_kind', 'certificate_number', 'certificate_type', 'competition_label', 'outcome_code', 'position', 'issued_at'];
        $projection = [];
        foreach ($allowed as $key) {
            $value = $entry[$key] ?? null;
            if (is_string($value) || is_int($value) || is_bool($value) || $value === null) {
                $projection[$key] = $value;
            }
        }
        if (!isset($projection['source_kind']) || !is_string($projection['source_kind'])) {
            throw new \InvalidArgumentException('Passport entry projection lacks a source kind.');
        }

        return $projection;
    }
}
