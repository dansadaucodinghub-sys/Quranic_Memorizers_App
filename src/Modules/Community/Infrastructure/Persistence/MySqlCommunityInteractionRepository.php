<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOStatement;
use Qmdb\Modules\Community\Application\CommunityInteractionRepository;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class MySqlCommunityInteractionRepository implements CommunityInteractionRepository
{
    public function __construct(private DatabaseConnectionProvider $connections)
    {
    }

    public function state(int $workspaceId, UuidV7 $clipId, int $actorId): array
    {
        if (!$this->connections->connection()->inTransaction()) {
            throw new \LogicException('Clip interaction reads require a transaction.');
        }
        $row = $this->query(
            'SELECT id FROM recitation_clips WHERE workspace_id=:workspace AND public_id=:public',
            ['workspace' => $workspaceId, 'public' => $clipId->toBinary()]
        )->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new \DomainException('Clip is unavailable.');
        }
        $clipInternalId = $this->integer($row, 'id');
        $result = [];
        foreach (['reaction' => 'REACTION', 'bookmark' => 'BOOKMARK'] as $key => $kind) {
            $last = $this->query(
                'SELECT event_code,interaction_version FROM community_interaction_events
                 WHERE clip_id=:clip AND actor_account_id=:actor AND interaction_kind=:kind
                 ORDER BY interaction_version DESC LIMIT 1',
                ['clip' => $clipInternalId, 'actor' => $actorId, 'kind' => $kind]
            )->fetch(PDO::FETCH_ASSOC);
            $result[$key] = [
                'active' => is_array($last) && ($last['event_code'] ?? null) === 'ADDED',
                'version' => is_array($last) ? $this->integer($last, 'interaction_version') : 0,
            ];
        }
        return ['reaction' => $result['reaction'], 'bookmark' => $result['bookmark']];
    }

    public function bookmarkedClipIds(int $actorId): array
    {
        if (!$this->connections->connection()->inTransaction()) {
            throw new \LogicException('Bookmark reads require a transaction.');
        }
        $rows = $this->query(
            'SELECT clip.public_id FROM community_bookmarks bookmark
             JOIN recitation_clips clip ON clip.workspace_id=bookmark.workspace_id AND clip.id=bookmark.clip_id
             WHERE bookmark.actor_account_id=:actor AND bookmark.revoked_at IS NULL
             ORDER BY bookmark.created_at DESC,bookmark.id DESC LIMIT 50',
            ['actor' => $actorId]
        )->fetchAll(PDO::FETCH_COLUMN);
        $result = [];
        foreach ($rows as $row) {
            if (!is_string($row)) {
                throw new \UnexpectedValueException('Bookmark identifier is malformed.');
            }
            $result[] = UuidV7::fromBinary($row);
        }
        return $result;
    }

    public function transition(
        int $workspaceId,
        UuidV7 $clipId,
        int $actorId,
        string $kind,
        string $action,
        int $expectedVersion,
        DateTimeImmutable $now
    ): array {
        if (!$this->connections->connection()->inTransaction()) {
            throw new \LogicException('Clip interactions require a transaction.');
        }
        if (
            !in_array($kind, ['REACTION', 'BOOKMARK'], true)
            || !in_array($action, ['ADD', 'REMOVE'], true) || $expectedVersion < 0
        ) {
            throw new \InvalidArgumentException('Clip interaction is invalid.');
        }
        $clip = $this->query(
            'SELECT id FROM recitation_clips WHERE workspace_id=:workspace AND public_id=:public AND status=\'PUBLISHED\' FOR UPDATE',
            ['workspace' => $workspaceId, 'public' => $clipId->toBinary()]
        )->fetch(PDO::FETCH_ASSOC);
        if (!is_array($clip)) {
            throw new \DomainException('Clip is unavailable.');
        }
        $internalId = $this->integer($clip, 'id');
        $last = $this->query(
            'SELECT interaction_version,event_code FROM community_interaction_events WHERE clip_id=:clip AND actor_account_id=:actor AND interaction_kind=:kind ORDER BY interaction_version DESC LIMIT 1 FOR UPDATE',
            ['clip' => $internalId, 'actor' => $actorId, 'kind' => $kind]
        )->fetch(PDO::FETCH_ASSOC);
        $version = is_array($last) ? $this->integer($last, 'interaction_version') : 0;
        $active = is_array($last) && $last['event_code'] === 'ADDED';
        if ($version !== $expectedVersion || ($action === 'ADD') === $active) {
            throw new \DomainException('Clip interaction changed. Reload before trying again.');
        }
        $table = $kind === 'REACTION' ? 'community_reactions' : 'community_bookmarks';
        $time = $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
        if ($action === 'ADD') {
            if ($kind === 'REACTION') {
                $this->query(
                    "INSERT INTO {$table} (workspace_id,clip_id,actor_account_id,reaction_code,created_at) VALUES (:workspace,:clip,:actor,'APPRECIATE',:created)",
                    ['workspace' => $workspaceId, 'clip' => $internalId, 'actor' => $actorId, 'created' => $time]
                );
            } else {
                $this->query(
                    "INSERT INTO {$table} (workspace_id,clip_id,actor_account_id,created_at) VALUES (:workspace,:clip,:actor,:created)",
                    ['workspace' => $workspaceId, 'clip' => $internalId, 'actor' => $actorId, 'created' => $time]
                );
            }
        } else {
            $updated = $this->query(
                "UPDATE {$table} SET revoked_at=:revoked WHERE clip_id=:clip AND actor_account_id=:actor AND revoked_at IS NULL",
                ['revoked' => $time, 'clip' => $internalId, 'actor' => $actorId]
            );
            if ($updated->rowCount() !== 1) {
                throw new \DomainException('Clip interaction changed. Reload before trying again.');
            }
        }
        $eventId = UuidV7::generate();
        $status = $action === 'ADD' ? 'ADDED' : 'REMOVED';
        $this->query(
            'INSERT INTO community_interaction_events (public_id,workspace_id,clip_id,actor_account_id,interaction_kind,event_code,interaction_version,created_at) VALUES (:public,:workspace,:clip,:actor,:kind,:event,:version,:created)',
            ['public' => $eventId->toBinary(), 'workspace' => $workspaceId, 'clip' => $internalId,
                'actor' => $actorId, 'kind' => $kind, 'event' => $status,
            'version' => $version + 1,
            'created' => $time]
        );
        return ['public_id' => $eventId->toString(), 'status' => $status, 'version' => $version + 1];
    }

    /** @param array<string,int|string|null> $parameters */
    private function query(string $sql, array $parameters): PDOStatement
    {
        $statement = $this->connections->connection()->prepare($sql);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Could not prepare Clip interaction statement.');
        }
        $statement->execute($parameters);
        return $statement;
    }

    /** @param array<array-key,mixed> $row */
    private function integer(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        return is_int($value) || (is_string($value) && ctype_digit($value))
            ? (int) $value : throw new \UnexpectedValueException('Clip interaction row is malformed.');
    }
}
