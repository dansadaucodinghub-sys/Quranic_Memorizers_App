<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace Qmdb\Modules\SecurityPrivilegedAccess\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessRequestCommand;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessRequestRepository;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessRequestId;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessRequestStatus;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class MySqlPrivilegedAccessRequestRepository implements PrivilegedAccessRequestRepository
{
    public function __construct(private DatabaseConnectionProvider $provider)
    {
    }

    public function create(
        PrivilegedAccessRequestCommand $command,
        DateTimeImmutable $now,
        DateTimeImmutable $expiresAt,
    ): PrivilegedAccessRequestId {
        $requestId = PrivilegedAccessRequestId::generate();
        $timestamp = self::format($now);
        $statement = $this->provider->connection()->prepare(<<<'SQL'
INSERT INTO privileged_access_requests
    (public_id, submission_id, access_type, scope_type, workspace_id, subject_account_id, subject_membership_id,
     requested_by_account_id, status, requested_duration_seconds, justification, reference_code, requested_locale,
     request_expires_at, version, created_at, updated_at)
VALUES
    (:public_id, :submission_id, :access_type, :scope_type, :workspace_id, :subject_account_id,
     :subject_membership_id, :requested_by_account_id, 'REQUESTED', :requested_duration, :justification,
     :reference_code, :requested_locale, :request_expires_at, 1, :created_at, :updated_at)
SQL);
        $statement->bindValue(':public_id', $requestId->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':submission_id', $command->submissionId->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':access_type', $command->type->value);
        $statement->bindValue(':scope_type', $command->scope->value);
        $statement->bindValue(':workspace_id', $command->workspaceInternalId, $command->workspaceInternalId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':subject_account_id', $command->actor->accountInternalId, PDO::PARAM_INT);
        $statement->bindValue(':subject_membership_id', $command->subjectMembershipInternalId, $command->subjectMembershipInternalId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':requested_by_account_id', $command->actor->accountInternalId, PDO::PARAM_INT);
        $statement->bindValue(':requested_duration', $command->duration->seconds, PDO::PARAM_INT);
        $statement->bindValue(':justification', $command->justification->reveal());
        $statement->bindValue(':reference_code', $command->reference?->value());
        $statement->bindValue(':requested_locale', $command->locale);
        $statement->bindValue(':request_expires_at', self::format($expiresAt));
        $statement->bindValue(':created_at', $timestamp);
        $statement->bindValue(':updated_at', $timestamp);
        try {
            $statement->execute();
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }

            return $this->replayOrReject($command, $exception);
        }
        $requestInternalId = (int) $this->provider->connection()->lastInsertId();
        if ($requestInternalId < 1) {
            throw new \UnexpectedValueException('Privileged-access request persistence failed.');
        }
        $grant = $this->provider->connection()->prepare(<<<'SQL'
INSERT INTO privileged_access_request_permissions
    (request_id, request_access_type, request_scope_type, permission_id, permission_scope_type, created_at)
SELECT :request_id, :access_type, :scope_type, permission_definition.id, permission_definition.scope_type, :created_at
FROM authorization_permissions permission_definition
INNER JOIN privileged_access_permission_policies policy
    ON policy.permission_id = permission_definition.id
    AND policy.permission_scope_type = permission_definition.scope_type
    AND policy.access_type = :policy_access_type
    AND policy.status = 'ACTIVE'
WHERE permission_definition.code = :permission_code
    AND permission_definition.scope_type = :permission_scope
    AND permission_definition.status = 'ACTIVE'
SQL);
        foreach ($command->permissions as $permission) {
            $grant->bindValue(':request_id', $requestInternalId, PDO::PARAM_INT);
            $grant->bindValue(':access_type', $command->type->value);
            $grant->bindValue(':scope_type', $command->scope->value);
            $grant->bindValue(':created_at', $timestamp);
            $grant->bindValue(':policy_access_type', $command->type->value);
            $grant->bindValue(':permission_code', $permission->value());
            $grant->bindValue(':permission_scope', $command->scope->value);
            $grant->execute();
            if ($grant->rowCount() !== 1) {
                throw new \DomainException('A requested privileged permission is not eligible.');
            }
        }
        $event = $this->provider->connection()->prepare(<<<'SQL'
INSERT INTO privileged_access_events
    (public_id, request_id, activation_id, event_type, actor_kind, actor_account_id, reason_code, correlation_id, occurred_at)
VALUES (:public_id, :request_id, NULL, 'REQUESTED', 'ACCOUNT', :actor_account_id, :reason_code, :correlation_id, :occurred_at)
SQL);
        $event->bindValue(':public_id', UuidV7::generate()->toBinary(), PDO::PARAM_LOB);
        $event->bindValue(':request_id', $requestInternalId, PDO::PARAM_INT);
        $event->bindValue(':actor_account_id', $command->actor->accountInternalId, PDO::PARAM_INT);
        $event->bindValue(':reason_code', match ($command->type->value) {
            'SUPPORT_ACCESS' => 'SUPPORT_REQUEST',
            'BREAK_GLASS' => 'INCIDENT_RESPONSE',
            default => 'TEMPORARY_OPERATIONAL_NEED',
        });
        $correlation = hex2bin($command->correlationId->value());
        if (!is_string($correlation)) {
            throw new \UnexpectedValueException('Privileged-access correlation identifier is invalid.');
        }
        $event->bindValue(':correlation_id', $correlation, PDO::PARAM_LOB);
        $event->bindValue(':occurred_at', $timestamp);
        $event->execute();

        return $requestId;
    }

    private function replayOrReject(PrivilegedAccessRequestCommand $command, PDOException $exception): PrivilegedAccessRequestId
    {
        $request = $this->provider->connection()->prepare(
            'SELECT id, public_id, access_type, scope_type, workspace_id, subject_account_id, subject_membership_id, '
            . 'requested_duration_seconds, justification, reference_code, requested_locale FROM privileged_access_requests '
            . 'WHERE submission_id = :submission_id LIMIT 1',
        );
        $request->bindValue(':submission_id', $command->submissionId->toBinary(), PDO::PARAM_LOB);
        $request->execute();
        $row = self::row($request->fetch(PDO::FETCH_ASSOC));
        if ($row === null || !self::matches($row, $command)) {
            throw new \DomainException('The privileged-access submission identifier was already used for a different request.', 0, $exception);
        }
        $permissions = $this->provider->connection()->prepare(
            'SELECT permission_definition.code FROM privileged_access_request_permissions snapshot '
            . 'INNER JOIN authorization_permissions permission_definition ON permission_definition.id = snapshot.permission_id '
            . 'WHERE snapshot.request_id = :request_id ORDER BY permission_definition.code',
        );
        $permissions->execute([':request_id' => self::integer($row, 'id')]);
        $persisted = [];
        while (is_array($permission = $permissions->fetch(PDO::FETCH_ASSOC))) {
            $code = $permission['code'] ?? null;
            if (!is_string($code)) {
                throw new \UnexpectedValueException('Privileged-access request persistence row is invalid.');
            }
            $persisted[] = $code;
        }
        $requested = array_map(static fn ($permission): string => $permission->value(), $command->permissions);
        sort($requested, SORT_STRING);
        if ($persisted !== $requested) {
            throw new \DomainException('The privileged-access submission identifier was already used for a different request.', 0, $exception);
        }

        return PrivilegedAccessRequestId::fromBinary(self::string($row, 'public_id'));
    }

    /** @param array<string, mixed> $row */
    private static function matches(array $row, PrivilegedAccessRequestCommand $command): bool
    {
        return self::string($row, 'access_type') === $command->type->value
            && self::string($row, 'scope_type') === $command->scope->value
            && self::nullableInteger($row, 'workspace_id') === $command->workspaceInternalId
            && self::integer($row, 'subject_account_id') === $command->actor->accountInternalId
            && self::nullableInteger($row, 'subject_membership_id') === $command->subjectMembershipInternalId
            && self::integer($row, 'requested_duration_seconds') === $command->duration->seconds
            && hash_equals(self::string($row, 'justification'), $command->justification->reveal())
            && self::nullableString($row, 'reference_code') === $command->reference?->value()
            && self::string($row, 'requested_locale') === $command->locale;
    }

    private static function format(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }

    /** @return array<string, mixed>|null */
    private static function row(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }
        $row = [];
        foreach ($value as $column => $field) {
            if (!is_string($column)) {
                throw new \UnexpectedValueException('Privileged-access request persistence row is invalid.');
            }
            $row[$column] = $field;
        }

        return $row;
    }

    /** @param array<string, mixed> $row */
    private static function string(array $row, string $column): string
    {
        $value = $row[$column] ?? null;
        if (!is_string($value)) {
            throw new \UnexpectedValueException('Privileged-access request persistence row is invalid.');
        }

        return $value;
    }

    /** @param array<string, mixed> $row */
    private static function nullableString(array $row, string $column): ?string
    {
        $value = $row[$column] ?? null;
        if ($value !== null && !is_string($value)) {
            throw new \UnexpectedValueException('Privileged-access request persistence row is invalid.');
        }

        return $value;
    }

    /** @param array<string, mixed> $row */
    private static function integer(array $row, string $column): int
    {
        $value = $row[$column] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value) || !ctype_digit($value)) {
            throw new \UnexpectedValueException('Privileged-access request persistence row is invalid.');
        }

        return (int) $value;
    }

    /** @param array<string, mixed> $row */
    private static function nullableInteger(array $row, string $column): ?int
    {
        return ($row[$column] ?? null) === null ? null : self::integer($row, $column);
    }
}
