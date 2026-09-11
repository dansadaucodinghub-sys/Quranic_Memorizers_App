<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Application;

use Normalizer;

final class QuranSearchQueryNormalizer
{
    public function normalize(string $mode, mixed $value): string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || str_contains($value, "\0") || strlen($value) > 256 || preg_match('/[\x{0000}-\x{001F}\x{007F}-\x{009F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', $value) === 1) {
            throw new \InvalidArgumentException('Search query is invalid.');
        }
        $query = $mode === 'SIMPLE' ? (Normalizer::normalize($value, Normalizer::FORM_C) ?: '') : $value;
        if (!is_string($query)) {
            throw new \InvalidArgumentException('Search query normalization failed.');
        }
        if ($mode === 'SIMPLE') {
            $query = preg_replace('/[\x{0640}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $query) ?? '';
            $query = preg_replace('/\s+/u', ' ', trim($query)) ?? '';
        }
        if ($query === '' || mb_strlen($query, 'UTF-8') > 80) {
            throw new \InvalidArgumentException('Search query length is invalid.');
        }
        return $query;
    }
}
