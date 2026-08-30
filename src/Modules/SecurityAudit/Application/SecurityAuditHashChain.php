<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Application;

use DateTimeImmutable;

final readonly class SecurityAuditHashChain
{
    public const string GENESIS_HASH = "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0";

    /** @param array<string, scalar|null> $fields */
    public function eventHash(array $fields, string $metadataHash, string $previousHash, string $key): string
    {
        if (strlen($metadataHash) !== 32 || strlen($previousHash) !== 32) {
            throw new \InvalidArgumentException('Security audit hash input is invalid.');
        }
        ksort($fields, SORT_STRING);
        $input = json_encode([
            'domain' => 'QMDB-AUDIT-EVENT-V1',
            'fields' => $fields,
            'metadata_hash' => bin2hex($metadataHash),
            'previous_event_hash' => bin2hex($previousHash),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return hash_hmac('sha256', $input, $key, true);
    }

    public function checkpointHash(
        string $publicId,
        int $number,
        int $streamCount,
        int $eventCount,
        string $headsDigest,
        string $previousHash,
        int $keyVersion,
        DateTimeImmutable $createdAt,
        string $key,
    ): string {
        $input = json_encode([
            'domain' => 'QMDB-AUDIT-CHECKPOINT-V1', 'checkpoint_public_id' => $publicId,
            'checkpoint_number' => $number, 'stream_count' => $streamCount, 'total_event_count' => $eventCount,
            'heads_digest' => bin2hex($headsDigest), 'previous_checkpoint_hash' => bin2hex($previousHash),
            'integrity_key_version' => $keyVersion, 'created_at' => self::timestamp($createdAt),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return hash_hmac('sha256', $input, $key, true);
    }

    /**
     * @param list<array{stream_public_id:string,stream_type:string,scope_public_id:?string,last_sequence:int,last_event_hash:?string}> $heads
     */
    public function headsDigest(array $heads): string
    {
        usort($heads, static fn (array $left, array $right): int => strcmp($left['stream_public_id'], $right['stream_public_id']));

        return hash('sha256', json_encode($heads, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), true);
    }

    public static function timestamp(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }
}
