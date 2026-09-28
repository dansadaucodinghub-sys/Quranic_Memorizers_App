<?php

declare(strict_types=1);

namespace Qmdb\Modules\PilotOfflineRollout\Application;

use Qmdb\Shared\Identifier\UuidV7;

/** Closed parser shared by all administrative P13 mutations. */
final readonly class P13MutationRequestParser
{
    /** @param array<array-key,mixed> $body */
    public function text(array $body, string $name, int $maximum, ?string $default = null): string
    {
        $value = $body[$name] ?? $default;
        if (!is_string($value) || trim($value) === '' || mb_strlen($value) > $maximum) {
            throw new \InvalidArgumentException('P13 mutation field is invalid: ' . $name);
        }

        return trim($value);
    }

    /** @param array<array-key,mixed> $body */
    public function optionalText(array $body, string $name, int $maximum): ?string
    {
        $value = $body[$name] ?? null;
        if ($value === null || $value === '') {
            return null;
        }

        return $this->text($body, $name, $maximum);
    }

    /** @param array<array-key,mixed> $body */
    public function integer(array $body, string $name): int
    {
        $value = $body[$name] ?? null;
        if ((!is_string($value) && !is_int($value)) || preg_match('/\A[1-9][0-9]{0,18}\z/', (string) $value) !== 1) {
            throw new \InvalidArgumentException('P13 mutation integer is invalid: ' . $name);
        }

        return (int) $value;
    }

    /** @param array<array-key,mixed> $body */
    public function boolean(array $body, string $name): bool
    {
        $value = $body[$name] ?? null;
        if ($value === null) {
            return false;
        }
        if (!in_array($value, [true, false, 1, 0, '1', '0'], true)) {
            throw new \InvalidArgumentException('P13 mutation boolean is invalid: ' . $name);
        }

        return in_array($value, [true, 1, '1'], true);
    }

    /** @param array<array-key,mixed> $body */
    public function uuid(array $body, string $name): UuidV7
    {
        return UuidV7::fromString($this->text($body, $name, 36));
    }

    /** @param array<array-key,mixed> $body */
    public function optionalUuid(array $body, string $name): ?UuidV7
    {
        return ($body[$name] ?? '') === '' ? null : $this->uuid($body, $name);
    }

    /**
     * @param array<array-key,mixed> $body
     * @return list<array{type:string,id:string,version:int,payload:array<string,mixed>}>
     */
    public function packageEntities(array $body): array
    {
        $json = $this->text($body, 'entities_json', 1_000_000);
        $decoded = json_decode($json, true, 128, JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || !array_is_list($decoded) || $decoded === []) {
            throw new \InvalidArgumentException('Offline package entities are required.');
        }
        $entities = [];
        foreach ($decoded as $entity) {
            if (!is_array($entity) || !is_string($entity['type'] ?? null) || !is_string($entity['id'] ?? null) || !is_int($entity['version'] ?? null) || !is_array($entity['payload'] ?? null)) {
                throw new \InvalidArgumentException('Offline package entity is invalid.');
            }
            /** @var array<string,mixed> $payload */
            $payload = $entity['payload'];
            $entities[] = ['type' => $entity['type'], 'id' => $entity['id'], 'version' => $entity['version'], 'payload' => $payload];
        }

        return $entities;
    }

    /** @param array<string,string> $parameters */
    public function routeUuid(array $parameters, string $name): UuidV7
    {
        $value = $parameters[$name] ?? null;
        if (!is_string($value)) {
            throw new \InvalidArgumentException('P13 route identifier is invalid: ' . $name);
        }

        return UuidV7::fromString($value);
    }
}
