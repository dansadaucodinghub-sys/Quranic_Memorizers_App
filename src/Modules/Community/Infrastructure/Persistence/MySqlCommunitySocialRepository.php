<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOStatement;
use Qmdb\Modules\Community\Application\CommunitySocialRepository;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

/** Account locks serialize graph changes, so block can dominate concurrent follows. */
final readonly class MySqlCommunitySocialRepository implements CommunitySocialRepository
{
    public function __construct(private DatabaseConnectionProvider $connections)
    {
    }

    public function state(int $actorId, UuidV7 $targetProfileId): array
    {
        $this->requireTransaction();
        $profile = $this->query(
            'SELECT id,managing_account_id FROM community_profiles WHERE public_id=:profile LIMIT 1',
            ['profile' => $targetProfileId->toBinary()]
        )->fetch(PDO::FETCH_ASSOC);
        if (!is_array($profile)) {
            throw new \DomainException('Social profile is unavailable.');
        }
        $targetId = $this->integer($profile, 'managing_account_id');
        $follow = $this->query(
            'SELECT status,version FROM community_follows WHERE follower_account_id=:actor AND followed_profile_id=:profile LIMIT 1',
            ['actor' => $actorId, 'profile' => $this->integer($profile, 'id')]
        )->fetch(PDO::FETCH_ASSOC);
        $block = $this->latestToggle($actorId, $targetId, 'BLOCKED', 'UNBLOCKED');
        $mute = $this->latestToggle($actorId, $targetId, 'MUTED', 'UNMUTED');
        return ['follow_status' => is_array($follow) && is_string($follow['status'] ?? null)
                ? $follow['status'] : '',
            'follow_version' => is_array($follow) ? $this->integer($follow, 'version') : 0,
            'block_active' => $block['active'], 'block_version' => $block['version'],
            'mute_active' => $mute['active'], 'mute_version' => $mute['version'],
            'own_profile' => $targetId === $actorId];
    }

    public function safetyList(int $actorId): array
    {
        $this->requireTransaction();
        $rows = $this->query(<<<'SQL'
SELECT p.public_id,p.alias,b.id AS block_id,m.id AS mute_id,
  (SELECT e.relationship_version FROM community_social_events e
   WHERE e.actor_account_id=:block_actor AND e.target_account_id=p.managing_account_id
     AND e.event_code IN ('BLOCKED','UNBLOCKED') ORDER BY e.id DESC LIMIT 1) AS block_version,
  (SELECT e.relationship_version FROM community_social_events e
   WHERE e.actor_account_id=:mute_actor AND e.target_account_id=p.managing_account_id
     AND e.event_code IN ('MUTED','UNMUTED') ORDER BY e.id DESC LIMIT 1) AS mute_version
FROM community_profiles p
LEFT JOIN community_blocks b ON b.blocker_account_id=:block_owner
  AND b.blocked_account_id=p.managing_account_id AND b.revoked_at IS NULL
LEFT JOIN community_mutes m ON m.muter_account_id=:mute_owner
  AND m.muted_account_id=p.managing_account_id AND m.revoked_at IS NULL
WHERE b.id IS NOT NULL OR m.id IS NOT NULL
ORDER BY p.id DESC LIMIT 50
SQL, ['block_actor' => $actorId, 'mute_actor' => $actorId,
            'block_owner' => $actorId, 'mute_owner' => $actorId])->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !is_string($row['public_id'] ?? null) || !is_string($row['alias'] ?? null)) {
                throw new \UnexpectedValueException('Community safety relationship is malformed.');
            }
            $result[] = ['profile_id' => UuidV7::fromBinary($row['public_id'])->toString(),
                'alias' => $row['alias'], 'blocked' => $row['block_id'] !== null,
                'muted' => $row['mute_id'] !== null,
                'block_version' => $row['block_version'] === null ? 0 : $this->integer($row, 'block_version'),
                'mute_version' => $row['mute_version'] === null ? 0 : $this->integer($row, 'mute_version')];
        }
        return $result;
    }

    public function incoming(int $actorId): array
    {
        $this->requireTransaction();
        $rows = $this->query(<<<'SQL'
SELECT follower.public_id,follower.alias,f.status,f.version
FROM community_profiles owned
JOIN community_follows f ON f.followed_profile_id=owned.id
JOIN community_profiles follower ON follower.managing_account_id=f.follower_account_id
WHERE owned.managing_account_id=:actor AND owned.status='ACTIVE'
  AND f.status IN ('PENDING','ACTIVE')
ORDER BY f.updated_at DESC,f.id DESC LIMIT 50
SQL, ['actor' => $actorId])->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            if (
                !is_array($row) || !is_string($row['public_id'] ?? null)
                || !is_string($row['alias'] ?? null) || !is_string($row['status'] ?? null)
            ) {
                throw new \UnexpectedValueException('Incoming follow row is malformed.');
            }
            $result[] = ['profile_id' => UuidV7::fromBinary($row['public_id'])->toString(),
                'alias' => $row['alias'], 'status' => $row['status'],
                'version' => $this->integer($row, 'version')];
        }
        return $result;
    }

    public function outgoing(int $actorId): array
    {
        $this->requireTransaction();
        $rows = $this->query(<<<'SQL'
SELECT target.public_id,target.alias,f.status,f.version
FROM community_follows f
JOIN community_profiles target ON target.id=f.followed_profile_id
WHERE f.follower_account_id=:actor AND f.status IN ('PENDING','ACTIVE')
ORDER BY f.updated_at DESC,f.id DESC LIMIT 50
SQL, ['actor' => $actorId])->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            if (
                !is_array($row) || !is_string($row['public_id'] ?? null)
                || !is_string($row['alias'] ?? null) || !is_string($row['status'] ?? null)
            ) {
                throw new \UnexpectedValueException('Outgoing follow row is malformed.');
            }
            $result[] = ['profile_id' => UuidV7::fromBinary($row['public_id'])->toString(),
                'alias' => $row['alias'], 'status' => $row['status'],
                'version' => $this->integer($row, 'version')];
        }
        return $result;
    }

    /** @return array{active:bool,version:int} */
    private function latestToggle(int $actorId, int $targetId, string $on, string $off): array
    {
        $row = $this->query(
            'SELECT event_code,relationship_version FROM community_social_events WHERE actor_account_id=:actor AND target_account_id=:target AND event_code IN (:on,:off) ORDER BY id DESC LIMIT 1',
            ['actor' => $actorId, 'target' => $targetId, 'on' => $on, 'off' => $off]
        )->fetch(PDO::FETCH_ASSOC);
        return ['active' => is_array($row) && $row['event_code'] === $on,
            'version' => is_array($row) ? $this->integer($row, 'relationship_version') : 0];
    }

    public function transition(
        int $actorId,
        UuidV7 $targetProfileId,
        string $action,
        int $expectedVersion,
        DateTimeImmutable $now
    ): array {
        $this->requireTransaction();
        if ($expectedVersion < 0 || $actorId < 1) {
            throw new \InvalidArgumentException('Social relationship version is invalid.');
        }
        $target = $this->profile($targetProfileId);
        $targetId = $this->integer($target, 'managing_account_id');
        if ($targetId === $actorId) {
            throw new \DomainException('Social relationship is unavailable.');
        }
        $this->lockAccounts($actorId, $targetId);
        if (in_array($action, ['FOLLOW', 'UNFOLLOW', 'ACCEPT', 'DECLINE', 'REVOKE_FOLLOWER'], true)) {
            return $this->follow($actorId, $targetId, $target, $action, $expectedVersion, $now);
        }
        return match ($action) {
            'BLOCK', 'UNBLOCK' => $this->toggle($actorId, $targetId, $action,
                'community_blocks', 'blocker_account_id', 'blocked_account_id', $expectedVersion, $now),
            'MUTE', 'UNMUTE' => $this->toggle($actorId, $targetId, $action,
                'community_mutes', 'muter_account_id', 'muted_account_id', $expectedVersion, $now),
            default => throw new \InvalidArgumentException('Unknown social relationship action.'),
        };
    }

    /** @param array<string,mixed> $target
     * @return array{public_id:string,status:string,version:int} */
    private function follow(
        int $actorId,
        int $targetId,
        array $target,
        string $action,
        int $expectedVersion,
        DateTimeImmutable $now
    ): array {
        $ownerAction = in_array($action, ['ACCEPT', 'DECLINE', 'REVOKE_FOLLOWER'], true);
        $followed = $ownerAction ? $this->ownedProfile($actorId) : $target;
        if ($action === 'FOLLOW') {
            $followerProfile = $this->ownedProfile($actorId);
            if (
                ($followerProfile['status'] ?? null) !== 'ACTIVE'
                || $this->integer($followerProfile, 'adult') !== 1
            ) {
                throw new \DomainException('An active adult community profile is required to follow.');
            }
        }
        $followerId = $ownerAction ? $targetId : $actorId;
        $followedId = $this->integer($followed, 'id');
        if (in_array($action, ['FOLLOW', 'ACCEPT'], true) && $this->integer($followed, 'adult') !== 1) {
            throw new \DomainException('Child profiles cannot accept public social relationships.');
        }
        if (($followed['status'] ?? null) !== 'ACTIVE' || ($target['status'] ?? null) !== 'ACTIVE') {
            throw new \DomainException('Social relationship is unavailable.');
        }
        if ($action !== 'UNFOLLOW' && $this->blocked($actorId, $targetId)) {
            throw new \DomainException('Social relationship is unavailable.');
        }
        $fetched = $this->query(
            'SELECT id,public_id,status,version FROM community_follows WHERE follower_account_id=:follower AND followed_profile_id=:profile FOR UPDATE',
            ['follower' => $followerId, 'profile' => $followedId]
        )->fetch(PDO::FETCH_ASSOC);
        $row = is_array($fetched) ? $fetched : null;
        $version = $row !== null ? $this->integer($row, 'version') : 0;
        $publicId = $row !== null && is_string($row['public_id'])
            ? UuidV7::fromBinary($row['public_id']) : UuidV7::generate();
        $newRelationship = $row === null;
        if ($version !== $expectedVersion) {
            throw new \DomainException('Social relationship changed. Reload before trying again.');
        }
        $previous = $row !== null ? $row['status'] : null;
        $status = match ($action) {
            'FOLLOW' => ($previous === null || in_array($previous, ['DECLINED', 'REVOKED'], true))
                ? (($followed['visibility'] ?? null) === 'PUBLIC' && in_array($followed['public_consent'] ?? null, [1, '1'], true) ? 'ACTIVE' : 'PENDING')
                : throw new \DomainException('Social relationship is unavailable.'),
            'UNFOLLOW' => in_array($previous, ['ACTIVE', 'PENDING'], true) ? 'REVOKED'
                : throw new \DomainException('Social relationship is unavailable.'),
            'ACCEPT' => $previous === 'PENDING' ? 'ACTIVE' : throw new \DomainException('Social relationship is unavailable.'),
            'DECLINE' => $previous === 'PENDING' ? 'DECLINED' : throw new \DomainException('Social relationship is unavailable.'),
            'REVOKE_FOLLOWER' => in_array($previous, ['ACTIVE', 'PENDING'], true) ? 'REVOKED'
                : throw new \DomainException('Social relationship is unavailable.'),
            default => throw new \LogicException('Invalid follow action.'),
        };
        $time = $this->time($now);
        if ($newRelationship) {
            $this->query(
                'INSERT INTO community_follows (public_id,follower_account_id,followed_profile_id,status,version,created_at,updated_at) VALUES (:public,:follower,:profile,:status,1,:created,:updated)',
                ['public' => $publicId->toBinary(), 'follower' => $followerId, 'profile' => $followedId,
                'status' => $status,
                'created' => $time,
                'updated' => $time]
            );
        } else {
            $this->query(
                'UPDATE community_follows SET status=:status,version=version+1,updated_at=:updated WHERE id=:id AND version=:version',
                ['status' => $status, 'updated' => $time, 'id' => $this->integer($row, 'id'), 'version' => $version]
            );
        }
        $eventCode = match ($action) {
            'FOLLOW' => $status === 'ACTIVE' ? 'FOLLOWED' : 'FOLLOW_REQUESTED',
            'ACCEPT' => 'FOLLOW_ACCEPTED', 'DECLINE' => 'FOLLOW_DECLINED',
            'UNFOLLOW' => 'UNFOLLOWED',
            default => 'FOLLOWER_REVOKED',
        };
        $this->event(
            $actorId,
            $ownerAction ? $followerId : $targetId,
            $followedId,
            $eventCode,
            $version + 1,
            $time
        );
        return ['public_id' => $publicId->toString(), 'status' => $status, 'version' => $version + 1];
    }

    /** @return array{public_id:string,status:string,version:int} */
    private function toggle(
        int $actorId,
        int $targetId,
        string $action,
        string $table,
        string $ownerColumn,
        string $targetColumn,
        int $expectedVersion,
        DateTimeImmutable $now
    ): array {
        $codeA = $table === 'community_blocks' ? 'BLOCKED' : 'MUTED';
        $codeB = $table === 'community_blocks' ? 'UNBLOCKED' : 'UNMUTED';
        $last = $this->query(
            'SELECT event_code,relationship_version FROM community_social_events WHERE actor_account_id=:actor AND target_account_id=:target AND event_code IN (:on,:off) ORDER BY id DESC LIMIT 1 FOR UPDATE',
            ['actor' => $actorId, 'target' => $targetId, 'on' => $codeA, 'off' => $codeB]
        )->fetch(PDO::FETCH_ASSOC);
        $version = is_array($last) ? $this->integer($last, 'relationship_version') : 0;
        if ($version !== $expectedVersion || ($action === 'BLOCK' || $action === 'MUTE') === (is_array($last) && $last['event_code'] === $codeA)) {
            throw new \DomainException('Social relationship changed. Reload before trying again.');
        }
        $time = $this->time($now);
        if ($action === 'BLOCK' || $action === 'MUTE') {
            $this->query(
                "INSERT INTO {$table} ({$ownerColumn},{$targetColumn},created_at) VALUES (:actor,:target,:created)",
                ['actor' => $actorId, 'target' => $targetId, 'created' => $time]
            );
            if ($action === 'BLOCK') {
                $this->terminateFollows($actorId, $targetId, $time);
            }
        } else {
            $updated = $this->query(
                "UPDATE {$table} SET revoked_at=:revoked WHERE {$ownerColumn}=:actor AND {$targetColumn}=:target AND revoked_at IS NULL",
                ['revoked' => $time, 'actor' => $actorId, 'target' => $targetId]
            );
            if ($updated->rowCount() !== 1) {
                throw new \DomainException('Social relationship changed. Reload before trying again.');
            }
        }
        $eventId = $this->event(
            $actorId,
            $targetId,
            null,
            ($action === 'BLOCK' || $action === 'MUTE') ? $codeA : $codeB,
            $version + 1,
            $time
        );
        return ['public_id' => $eventId->toString(), 'status' => $action, 'version' => $version + 1];
    }

    private function terminateFollows(int $actorId, int $targetId, string $time): void
    {
        $rows = $this->query(
            "SELECT f.id,f.version,f.follower_account_id,f.followed_profile_id,p.managing_account_id FROM community_follows f JOIN community_profiles p ON p.id=f.followed_profile_id WHERE ((f.follower_account_id=:actor AND p.managing_account_id=:target) OR (f.follower_account_id=:target2 AND p.managing_account_id=:actor2)) AND f.status IN ('ACTIVE','PENDING') FOR UPDATE",
            ['actor' => $actorId, 'target' => $targetId, 'target2' => $targetId, 'actor2' => $actorId]
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $version = $this->integer($row, 'version');
            $this->query(
                "UPDATE community_follows SET status='REVOKED',version=version+1,updated_at=:updated WHERE id=:id AND version=:version",
                ['updated' => $time, 'id' => $this->integer($row, 'id'), 'version' => $version]
            );
            $this->event(
                $actorId,
                $targetId,
                $this->integer($row, 'followed_profile_id'),
                'FOLLOWER_REVOKED',
                $version + 1,
                $time
            );
        }
    }

    /** @return array<string,mixed> */
    private function profile(UuidV7 $id): array
    {
        $row = $this->query(
            "SELECT p.id,p.managing_account_id,p.visibility,p.status,p.public_consent,(person.birth_date IS NOT NULL AND person.birth_date<=DATE_SUB(UTC_DATE(), INTERVAL 18 YEAR)) AS adult FROM community_profiles p JOIN people_account_links l ON l.person_id=p.person_id AND l.account_id=p.managing_account_id AND l.link_type='SELF' AND l.status='ACTIVE' JOIN people_persons person ON person.id=p.person_id AND person.status='ACTIVE' JOIN user_accounts a ON a.id=p.managing_account_id AND a.account_status='ACTIVE' WHERE p.public_id=:public",
            ['public' => $id->toBinary()]
        )->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new \DomainException('Social profile is unavailable.');
        }
        return ['id' => $this->integer($row, 'id'),
            'managing_account_id' => $this->integer($row, 'managing_account_id'),
            'visibility' => $row['visibility'], 'status' => $row['status'],
            'public_consent' => $row['public_consent'],
            'adult' => $this->integer($row, 'adult')];
    }

    /** @return array<string,mixed> */
    private function ownedProfile(int $accountId): array
    {
        $row = $this->query(
            'SELECT p.id,p.managing_account_id,p.visibility,p.status,p.public_consent,(person.birth_date IS NOT NULL AND person.birth_date<=DATE_SUB(UTC_DATE(), INTERVAL 18 YEAR)) AS adult FROM community_profiles p JOIN people_persons person ON person.id=p.person_id WHERE p.managing_account_id=:actor',
            ['actor' => $accountId]
        )->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new \DomainException('Social profile is unavailable.');
        }
        return ['id' => $this->integer($row, 'id'),
            'managing_account_id' => $this->integer($row, 'managing_account_id'),
            'visibility' => $row['visibility'], 'status' => $row['status'],
            'public_consent' => $row['public_consent'],
            'adult' => $this->integer($row, 'adult')];
    }

    private function blocked(int $first, int $second): bool
    {
        return $this->query(
            'SELECT id FROM community_blocks WHERE revoked_at IS NULL AND ((blocker_account_id=:first AND blocked_account_id=:second) OR (blocker_account_id=:second2 AND blocked_account_id=:first2)) LIMIT 1',
            ['first' => $first, 'second' => $second, 'second2' => $second, 'first2' => $first]
        )->fetchColumn() !== false;
    }

    private function lockAccounts(int $first, int $second): void
    {
        $rows = $this->query(
            "SELECT id FROM user_accounts WHERE id IN (:first,:second) AND account_status='ACTIVE' ORDER BY id FOR UPDATE",
            ['first' => $first, 'second' => $second]
        )->fetchAll(PDO::FETCH_COLUMN);
        if (count($rows) !== 2) {
            throw new \DomainException('Social relationship is unavailable.');
        }
    }

    private function event(
        int $actorId,
        int $targetId,
        ?int $profileId,
        string $code,
        int $version,
        string $time
    ): UuidV7 {
        $id = UuidV7::generate();
        $this->query(
            'INSERT INTO community_social_events (public_id,actor_account_id,target_account_id,profile_id,event_code,relationship_version,created_at) VALUES (:public,:actor,:target,:profile,:code,:version,:created)',
            ['public' => $id->toBinary(), 'actor' => $actorId, 'target' => $targetId,
            'profile' => $profileId,
            'code' => $code,
            'version' => $version,
            'created' => $time]
        );
        return $id;
    }

    /** @param array<string,int|string|null> $parameters */
    private function query(string $sql, array $parameters): PDOStatement
    {
        $statement = $this->connections->connection()->prepare($sql);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Could not prepare social relationship statement.');
        }
        $statement->execute($parameters);
        return $statement;
    }

    /** @param array<array-key,mixed> $row */
    private function integer(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        return is_int($value) || (is_string($value) && ctype_digit($value))
            ? (int) $value : throw new \UnexpectedValueException('Social relationship row is malformed.');
    }

    private function requireTransaction(): void
    {
        if (!$this->connections->connection()->inTransaction()) {
            throw new \LogicException('Social relationship mutations require a transaction.');
        }
    }

    private function time(DateTimeImmutable $now): string
    {
        return $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }
}
