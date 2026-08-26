<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitAttempt;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitDecision;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use UnexpectedValueException;

final readonly class MySqlIdentityRateLimiter implements IdentityRateLimiter
{
    public function __construct(
        private DatabaseConnectionProvider $provider,
        private TransactionManager $transactions,
    ) {
    }

    public function consume(array $attempts, DateTimeImmutable $now): IdentityRateLimitDecision
    {
        usort($attempts, static function (IdentityRateLimitAttempt $left, IdentityRateLimitAttempt $right): int {
            return ($left->scope->value . bin2hex($left->fingerprint->toBinary()))
                <=> ($right->scope->value . bin2hex($right->fingerprint->toBinary()));
        });

        return $this->transactions->transactional(function () use ($attempts, $now): IdentityRateLimitDecision {
            $retryAfter = 0;
            foreach ($attempts as $attempt) {
                $decision = $this->consumeOne($attempt, $now);
                if (!$decision->allowed) {
                    $retryAfter = max($retryAfter, $decision->retryAfterSeconds);
                }
            }

            return $retryAfter > 0
                ? IdentityRateLimitDecision::throttled($retryAfter)
                : IdentityRateLimitDecision::allowed();
        });
    }

    public function reset(array $attempts): void
    {
        if ($attempts === []) {
            return;
        }
        $this->transactions->transactional(function () use ($attempts): void {
            $statement = $this->provider->connection()->prepare(
                'DELETE FROM identity_rate_limit_buckets WHERE scope = :scope AND bucket_hash = :bucket_hash',
            );
            foreach ($attempts as $attempt) {
                $statement->bindValue(':scope', $attempt->scope->value);
                $statement->bindValue(':bucket_hash', $attempt->fingerprint->toBinary(), PDO::PARAM_LOB);
                $statement->execute();
            }
        });
    }

    private function consumeOne(
        IdentityRateLimitAttempt $attempt,
        DateTimeImmutable $now,
    ): IdentityRateLimitDecision {
        $connection = $this->provider->connection();
        $insert = $connection->prepare(
            'INSERT INTO identity_rate_limit_buckets '
            . '(scope, bucket_hash, window_started_at, attempt_count, blocked_until, version, created_at, updated_at) '
            . 'VALUES (:scope, :bucket_hash, :window_started_at, 0, NULL, 1, :created_at, :updated_at) '
            . 'ON DUPLICATE KEY UPDATE version = version',
        );
        $insert->bindValue(':scope', $attempt->scope->value);
        $insert->bindValue(':bucket_hash', $attempt->fingerprint->toBinary(), PDO::PARAM_LOB);
        $formattedNow = self::format($now);
        $insert->bindValue(':window_started_at', $formattedNow);
        $insert->bindValue(':created_at', $formattedNow);
        $insert->bindValue(':updated_at', $formattedNow);
        $insert->execute();

        $select = $connection->prepare(
            'SELECT window_started_at, attempt_count, blocked_until FROM identity_rate_limit_buckets '
            . 'WHERE scope = :scope AND bucket_hash = :bucket_hash FOR UPDATE',
        );
        $select->bindValue(':scope', $attempt->scope->value);
        $select->bindValue(':bucket_hash', $attempt->fingerprint->toBinary(), PDO::PARAM_LOB);
        $select->execute();
        $row = self::associativeRow($select->fetch(PDO::FETCH_ASSOC));
        if ($row === null) {
            throw new UnexpectedValueException('Rate-limit persistence row is missing.');
        }
        $windowStarted = new DateTimeImmutable(self::requiredString($row, 'window_started_at'));
        $blockedRaw = $row['blocked_until'] ?? null;
        $blockedUntil = is_string($blockedRaw) ? new DateTimeImmutable($blockedRaw) : null;
        if ($blockedUntil !== null && $blockedUntil > $now) {
            return IdentityRateLimitDecision::throttled($blockedUntil->getTimestamp() - $now->getTimestamp());
        }
        $windowExpired = ($now->getTimestamp() - $windowStarted->getTimestamp()) >= $attempt->policy->windowSeconds;
        $count = $windowExpired ? 1 : self::requiredInteger($row, 'attempt_count') + 1;
        $blockedUntil = $count > $attempt->policy->maximumAttempts
            ? $now->modify('+' . $attempt->policy->blockSeconds . ' seconds')
            : null;
        $update = $connection->prepare(
            'UPDATE identity_rate_limit_buckets SET window_started_at = :window_started_at, '
            . 'attempt_count = :attempt_count, blocked_until = :blocked_until, version = version + 1, '
            . 'updated_at = :updated_at WHERE scope = :scope AND bucket_hash = :bucket_hash',
        );
        $update->bindValue(':window_started_at', self::format($windowExpired ? $now : $windowStarted));
        $update->bindValue(':attempt_count', $count, PDO::PARAM_INT);
        $update->bindValue(':blocked_until', $blockedUntil === null ? null : self::format($blockedUntil));
        $update->bindValue(':updated_at', self::format($now));
        $update->bindValue(':scope', $attempt->scope->value);
        $update->bindValue(':bucket_hash', $attempt->fingerprint->toBinary(), PDO::PARAM_LOB);
        $update->execute();

        return $blockedUntil === null
            ? IdentityRateLimitDecision::allowed()
            : IdentityRateLimitDecision::throttled($attempt->policy->blockSeconds);
    }

    private static function format(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }

    /** @return array<string, mixed>|null */
    private static function associativeRow(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }
        $row = [];
        foreach ($value as $column => $field) {
            if (!is_string($column)) {
                throw new UnexpectedValueException('Rate-limit persistence row is invalid.');
            }
            $row[$column] = $field;
        }

        return $row;
    }

    /** @param array<string, mixed> $row */
    private static function requiredString(array $row, string $column): string
    {
        $value = $row[$column] ?? null;
        if (!is_string($value)) {
            throw new UnexpectedValueException('Rate-limit persistence row is invalid.');
        }

        return $value;
    }

    /** @param array<string, mixed> $row */
    private static function requiredInteger(array $row, string $column): int
    {
        $value = $row[$column] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value) || preg_match('/\A[0-9]+\z/', $value) !== 1) {
            throw new UnexpectedValueException('Rate-limit persistence row is invalid.');
        }

        return (int)$value;
    }
}
