<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOStatement;
use Qmdb\Modules\Community\Application\CommunityProfileRepository;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class MySqlCommunityProfileRepository implements CommunityProfileRepository
{
    public function __construct(private DatabaseConnectionProvider $connections)
    {
    }

    public function mine(int $accountId): ?array
    {
        $this->requireTransaction();
        $row = $this->query(
            "SELECT p.public_id,p.alias,p.visibility,p.version FROM community_profiles p JOIN people_persons person ON person.id=p.person_id AND person.status='ACTIVE' JOIN people_account_links link ON link.person_id=p.person_id AND link.account_id=:account AND link.link_type='SELF' AND link.status='ACTIVE' WHERE p.managing_account_id=:manager AND p.status='ACTIVE' LIMIT 1",
            ['account' => $accountId, 'manager' => $accountId]
        )->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        if (!is_string($row['public_id']) || !is_string($row['alias']) || !is_string($row['visibility'])) {
            throw new \UnexpectedValueException('Own community profile is malformed.');
        }
        return ['public_id' => UuidV7::fromBinary($row['public_id'])->toString(),
            'alias' => $row['alias'], 'visibility' => $row['visibility'],
            'version' => $this->integer($row, 'version')];
    }

    public function createPrivate(int $accountId, string $alias, DateTimeImmutable $now): array
    {
        $this->requireTransaction();
        $person = $this->person($accountId);
        $existing = $this->query(
            'SELECT id FROM community_profiles WHERE person_id=:person FOR UPDATE',
            ['person' => $person['id']]
        )->fetchColumn();
        if ($existing !== false) {
            throw new \DomainException('A profile already exists for this Person.');
        }
        $id = UuidV7::generate();
        $time = $this->time($now);
        $this->query(
            "INSERT INTO community_profiles (public_id,person_id,managing_account_id,alias,visibility,status,public_consent,version,created_at,updated_at) VALUES (:public,:person,:account,:alias,'PRIVATE','ACTIVE',0,1,:created,:updated)",
            ['public' => $id->toBinary(), 'person' => $person['id'], 'account' => $accountId,
            'alias' => $alias,
            'created' => $time,
            'updated' => $time]
        );
        $profileId = (int) $this->connections->connection()->lastInsertId();
        $this->event($profileId, $accountId, 'CREATED', 1, $time);
        return ['public_id' => $id->toString(), 'visibility' => 'PRIVATE', 'version' => 1];
    }

    public function changeVisibility(
        int $accountId,
        UuidV7 $profileId,
        int $expectedVersion,
        string $visibility,
        DateTimeImmutable $now
    ): array {
        $this->requireTransaction();
        $person = $this->person($accountId);
        $statement = $this->query(
            'SELECT id,version,status,visibility FROM community_profiles WHERE public_id=:public AND person_id=:person AND managing_account_id=:account FOR UPDATE',
            ['public' => $profileId->toBinary(), 'person' => $person['id'], 'account' => $accountId],
        );
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || $row['status'] !== 'ACTIVE') {
            throw new \DomainException('Profile is unavailable.');
        }
        $version = $this->integer($row, 'version');
        if ($version !== $expectedVersion) {
            throw new \DomainException('Profile changed. Reload before trying again.');
        }
        if ($visibility === 'PUBLIC' && !$this->isVerifiedAdult($person['birth_date'], $now)) {
            throw new \DomainException('Public profile consent requires verified adult status or a governed guardian approval.');
        }
        $time = $this->time($now);
        $this->query(
            'UPDATE community_profiles SET visibility=:visibility,public_consent=:consent,version=version+1,updated_at=:time WHERE id=:id AND version=:version',
            ['visibility' => $visibility, 'consent' => $visibility === 'PUBLIC' ? 1 : 0,
            'time' => $time,
            'id' => $this->integer(
                $row,
                'id'
            ),
            'version' => $version]
        );
        $this->event($this->integer($row, 'id'), $accountId, 'VISIBILITY_CHANGED', $version + 1, $time);
        return ['public_id' => $profileId->toString(), 'visibility' => $visibility, 'version' => $version + 1];
    }

    public function updateOwn(
        int $accountId,
        UuidV7 $profileId,
        int $expectedVersion,
        string $alias,
        string $visibility,
        DateTimeImmutable $now
    ): array {
        $this->requireTransaction();
        $person = $this->person($accountId);
        $row = $this->query(
            'SELECT id,version,status FROM community_profiles WHERE public_id=:public AND person_id=:person AND managing_account_id=:account FOR UPDATE',
            ['public' => $profileId->toBinary(), 'person' => $person['id'], 'account' => $accountId]
        )->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || $row['status'] !== 'ACTIVE') {
            throw new \DomainException('Profile is unavailable.');
        }
        $version = $this->integer($row, 'version');
        if ($version !== $expectedVersion) {
            throw new \DomainException('Profile changed. Reload before trying again.');
        }
        if ($visibility === 'PUBLIC' && !$this->isVerifiedAdult($person['birth_date'], $now)) {
            throw new \DomainException('Public profile consent requires verified adult status.');
        }
        $time = $this->time($now);
        $updated = $this->query(
            'UPDATE community_profiles SET alias=:alias,visibility=:visibility,public_consent=:consent,version=version+1,updated_at=:time WHERE id=:id AND version=:version',
            ['alias' => $alias, 'visibility' => $visibility,
                'consent' => $visibility === 'PUBLIC' ? 1 : 0, 'time' => $time,
                'id' => $this->integer($row, 'id'), 'version' => $version]
        );
        if ($updated->rowCount() !== 1) {
            throw new \DomainException('Profile changed concurrently.');
        }
        $this->event($this->integer($row, 'id'), $accountId, 'PROFILE_UPDATED', $version + 1, $time);
        return ['public_id' => $profileId->toString(), 'visibility' => $visibility, 'version' => $version + 1];
    }

    /** @return array{id:int,birth_date:?string} */
    private function person(int $accountId): array
    {
        $row = $this->query(
            "SELECT p.id,p.birth_date FROM people_account_links l JOIN people_persons p ON p.id=l.person_id WHERE l.account_id=:account AND l.link_type='SELF' AND l.status='ACTIVE' AND p.status='ACTIVE' FOR UPDATE",
            ['account' => $accountId],
        )->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || ($row['birth_date'] !== null && !is_string($row['birth_date']))) {
            throw new \DomainException('An active self-linked Person is required.');
        }
        return ['id' => $this->integer($row, 'id'), 'birth_date' => $row['birth_date']];
    }

    private function isVerifiedAdult(?string $birthDate, DateTimeImmutable $now): bool
    {
        if ($birthDate === null) {
            return false;
        }
        $birth = DateTimeImmutable::createFromFormat('!Y-m-d', $birthDate, new DateTimeZone('UTC'));
        return $birth !== false && $birth <= $now->setTimezone(new DateTimeZone('UTC'))->modify('-18 years');
    }

    private function event(int $profileId, int $actor, string $code, int $version, string $time): void
    {
        $this->query(
            'INSERT INTO community_profile_events (public_id,profile_id,actor_account_id,event_code,profile_version,created_at) VALUES (:public,:profile,:actor,:code,:version,:created)',
            ['public' => UuidV7::generate()->toBinary(), 'profile' => $profileId, 'actor' => $actor,
            'code' => $code,
            'version' => $version,
            'created' => $time]
        );
    }

    /** @param array<string,int|string|null> $parameters */
    private function query(string $sql, array $parameters): PDOStatement
    {
        $statement = $this->connections->connection()->prepare($sql);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Could not prepare profile statement.');
        }
        $statement->execute($parameters);
        return $statement;
    }

    /** @param array<array-key,mixed> $row */
    private function integer(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        return is_int($value) || (is_string($value) && ctype_digit($value))
            ? (int) $value : throw new \UnexpectedValueException('Profile row is malformed.');
    }

    private function requireTransaction(): void
    {
        if (!$this->connections->connection()->inTransaction()) {
            throw new \LogicException('Profile mutations require a transaction.');
        }
    }

    private function time(DateTimeImmutable $now): string
    {
        return $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }
}
