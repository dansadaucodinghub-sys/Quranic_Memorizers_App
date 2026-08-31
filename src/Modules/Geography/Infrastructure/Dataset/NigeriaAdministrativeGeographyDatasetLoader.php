<?php

declare(strict_types=1);

namespace Qmdb\Modules\Geography\Infrastructure\Dataset;

use JsonException;
use RuntimeException;

final readonly class NigeriaAdministrativeGeographyDatasetLoader
{
    public function __construct(private string $projectRoot)
    {
    }

    public function load(): NigeriaAdministrativeGeographyDataset
    {
        $path = $this->projectRoot . '/database/reference/nigeria-administrative-areas-v1.json';
        $raw = file_get_contents($path);
        if (!is_string($raw) || $raw === '' || !mb_check_encoding($raw, 'UTF-8') || str_contains($raw, "\0")) {
            throw new RuntimeException('Nigeria geography dataset file is unavailable or invalid.');
        }
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;

        try {
            $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Nigeria geography dataset JSON is invalid.', 0, $exception);
        }
        return NigeriaAdministrativeGeographyDataset::fromDecodedPayload($payload);
    }
}
