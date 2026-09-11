<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Infrastructure\Persistence;

use PDO;
use Qmdb\Modules\QuranReferenceGovernance\Application\QuranReleaseGovernanceReadRepository;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class MySqlQuranReleaseGovernanceReadRepository implements QuranReleaseGovernanceReadRepository
{
    public function __construct(private DatabaseConnectionProvider $connections)
    {
    }

    public function listRecent(int $limit): array
    {
        if ($limit < 1 || $limit > 100) {
            throw new \InvalidArgumentException('Release list limit is invalid.');
        }

        $statement = $this->connections->connection()->prepare(
            'SELECT BIN_TO_UUID(public_id) AS public_id, release_code, release_version, status, version, created_at '
            . 'FROM quran_reference_releases ORDER BY created_at DESC, id DESC LIMIT :limit',
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $releases = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \RuntimeException('Qur’an release query returned an invalid row.');
            }
            $releases[] = [
                'public_id' => $this->string($row, 'public_id'),
                'release_code' => $this->string($row, 'release_code'),
                'release_version' => $this->string($row, 'release_version'),
                'status' => $this->string($row, 'status'),
                'version' => $this->integer($row, 'version'),
                'created_at' => $this->string($row, 'created_at'),
            ];
        }

        return $releases;
    }

    public function find(UuidV7 $publicId): ?array
    {
        $statement = $this->connections->connection()->prepare(
            'SELECT BIN_TO_UUID(public_id) AS public_id, release_code, release_version, status, version, '
            . 'created_at, updated_at FROM quran_reference_releases WHERE public_id = :public_id',
        );
        $statement->bindValue(':public_id', $publicId->toBinary(), PDO::PARAM_LOB);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        return [
            'public_id' => $this->string($row, 'public_id'),
            'release_code' => $this->string($row, 'release_code'),
            'release_version' => $this->string($row, 'release_version'),
            'status' => $this->string($row, 'status'),
            'version' => $this->integer($row, 'version'),
            'created_at' => $this->string($row, 'created_at'),
            'updated_at' => $this->string($row, 'updated_at'),
        ];
    }

    /** @param array<mixed, mixed> $row */
    private function string(array $row, string $field): string
    {
        $value = $row[$field] ?? null;
        if (!is_string($value)) {
            throw new \RuntimeException('Qur’an release query returned an invalid ' . $field . ' value.');
        }

        return $value;
    }

    /** @param array<mixed, mixed> $row */
    private function integer(array $row, string $field): int
    {
        $value = $row[$field] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/\A(?:0|[1-9][0-9]*)\z/', $value) === 1) {
            return (int) $value;
        }

        throw new \RuntimeException('Qur’an release query returned an invalid ' . $field . ' value.');
    }
}
