<?php

declare(strict_types=1);

namespace Qmdb\Modules\Geography\Infrastructure\Dataset;

use RuntimeException;

final readonly class NigeriaAdministrativeGeographyDataset
{
    /** @param array<string, mixed> $payload */
    private function __construct(private array $payload)
    {
    }

    public static function fromDecodedPayload(mixed $payload): self
    {
        return new self(self::map($payload));
    }

    /** @return array<string, mixed> */
    public function metadata(): array
    {
        return $this->array('metadata');
    }

    /** @return array<string, mixed> */
    public function country(): array
    {
        return $this->array('country');
    }

    /** @return list<array<string, mixed>> */
    public function areas(): array
    {
        $rawAreas = $this->payload['areas'] ?? null;
        if (!is_array($rawAreas) || !array_is_list($rawAreas)) {
            throw new RuntimeException('Nigeria geography dataset areas are invalid.');
        }

        $areas = [];
        foreach ($rawAreas as $area) {
            $areas[] = self::map($area);
        }

        return $areas;
    }

    public function contentChecksum(): string
    {
        $encoded = json_encode(
            ['country' => $this->country(), 'areas' => $this->areas()],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );

        return hash('sha256', $encoded);
    }

    /** @return array<string, mixed> */
    private function array(string $key): array
    {
        return self::map($this->payload[$key] ?? null);
    }

    /** @return array<string, mixed> */
    private static function map(mixed $value): array
    {
        if (!is_array($value) || array_is_list($value)) {
            throw new RuntimeException('Nigeria geography dataset is invalid.');
        }

        $map = [];
        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                throw new RuntimeException('Nigeria geography dataset map has an invalid key.');
            }
            $map[$key] = $item;
        }

        return $map;
    }
}
