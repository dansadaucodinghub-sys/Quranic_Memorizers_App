<?php

declare(strict_types=1);

namespace Qmdb\Modules\TrustedArchive\Domain;

/** Deterministic SHA-256 chain; hashes are checksums, not digital signatures. */
final class TrustedArchiveHasher
{
    public function recordHash(int $sequence, string $manifestSha256, ?string $previousRecordSha256): string
    {
        if ($sequence < 1 || strlen($manifestSha256) !== 32 || ($previousRecordSha256 !== null && strlen($previousRecordSha256) !== 32)) {
            throw new \InvalidArgumentException('Trusted archive hash input is invalid.');
        }

        return hash('sha256', "QMDB-TRUSTED-ARCHIVE\0" . pack('J', $sequence) . $manifestSha256 . ($previousRecordSha256 ?? str_repeat("\0", 32)), true);
    }

    /** @param list<array{sequence:int,manifest_sha256:string,previous_record_sha256:?string,record_sha256:string}> $records */
    public function verifyChain(array $records): bool
    {
        $previous = null;
        foreach ($records as $record) {
            if ($record['previous_record_sha256'] !== $previous || !hash_equals($record['record_sha256'], $this->recordHash($record['sequence'], $record['manifest_sha256'], $previous))) {
                return false;
            }
            $previous = $record['record_sha256'];
        }

        return true;
    }
}
