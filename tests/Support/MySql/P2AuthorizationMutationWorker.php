<?php

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols -- isolated concurrent test worker

$payload = json_decode((string) stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (!is_array($payload) || !is_string($payload['operation'] ?? null)) {
    throw new RuntimeException('Authorization mutation worker payload is invalid.');
}
$dsn = 'mysql:host=' . (string) getenv('QMDB_TEST_DB_HOST')
    . ';port=' . (string) getenv('QMDB_TEST_DB_PORT')
    . ';dbname=' . (string) getenv('QMDB_TEST_DB_NAME')
    . ';charset=utf8mb4';
$connection = new \PDO(
    $dsn,
    (string) getenv('QMDB_TEST_DB_USERNAME'),
    (string) getenv('QMDB_TEST_DB_PASSWORD'),
    [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_EMULATE_PREPARES => false,
        \PDO::ATTR_STRINGIFY_FETCHES => false,
    ],
);
$connection->exec("SET SESSION time_zone = '+00:00'");
$operation = $payload['operation'];
$mutated = false;
try {
    $connection->beginTransaction();
    $mutated = match ($operation) {
        'assign_platform' => assignPlatform($connection, $payload),
        'assign_workspace' => assignWorkspace($connection, $payload),
        'revoke_platform_administrator' => revokeProtected(
            $connection,
            $payload,
            'platform_role_assignments',
            'platform.security_administrator',
        ),
        'revoke_workspace_owner' => revokeProtected(
            $connection,
            $payload,
            'workspace_role_assignments',
            'workspace.owner',
        ),
        'revoke_platform_authority_delayed' => revokePlatformAuthorityDelayed($connection, $payload),
        default => throw new RuntimeException('Authorization mutation worker operation is invalid.'),
    };
    $connection->commit();
} catch (\PDOException $exception) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
    if (($exception->errorInfo[0] ?? null) !== '23000') {
        throw $exception;
    }
}

echo json_encode(['mutated' => $mutated], JSON_THROW_ON_ERROR);

/** @param array<mixed> $payload */
function assignPlatform(\PDO $connection, array $payload): bool
{
    $accountId = positiveInt($payload, 'account_id');
    $roleId = positiveInt($payload, 'role_id');
    $actorId = positiveInt($payload, 'actor_id');
    $statement = $connection->prepare('INSERT INTO platform_role_assignments '
        . '(public_id, account_id, role_id, role_scope_type, status, version, assigned_by_kind, '
        . 'assigned_by_account_id, assignment_reason_code, assigned_at, correlation_id, created_at, updated_at) '
        . "VALUES (UUID_TO_BIN(UUID()), :account_id, :role_id, 'PLATFORM', 'ACTIVE', 1, 'ACCOUNT', "
        . ":actor_id, 'SECURITY_ADMINISTRATION', UTC_TIMESTAMP(6), UUID_TO_BIN(UUID()), "
        . 'UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))');
    $statement->execute([':account_id' => $accountId, ':role_id' => $roleId, ':actor_id' => $actorId]);

    return $statement->rowCount() === 1;
}

/** @param array<mixed> $payload */
function assignWorkspace(\PDO $connection, array $payload): bool
{
    $workspaceId = positiveInt($payload, 'workspace_id');
    $membershipId = positiveInt($payload, 'membership_id');
    $roleId = positiveInt($payload, 'role_id');
    $actorId = positiveInt($payload, 'actor_id');
    $statement = $connection->prepare('INSERT INTO workspace_role_assignments '
        . '(public_id, workspace_id, membership_id, role_id, role_scope_type, status, version, assigned_by_kind, '
        . 'assigned_by_account_id, assignment_reason_code, assigned_at, correlation_id, created_at, updated_at) '
        . "VALUES (UUID_TO_BIN(UUID()), :workspace_id, :membership_id, :role_id, 'WORKSPACE', 'ACTIVE', 1, "
        . "'ACCOUNT', :actor_id, 'SECURITY_ADMINISTRATION', UTC_TIMESTAMP(6), UUID_TO_BIN(UUID()), "
        . 'UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))');
    $statement->execute([
        ':workspace_id' => $workspaceId,
        ':membership_id' => $membershipId,
        ':role_id' => $roleId,
        ':actor_id' => $actorId,
    ]);

    return $statement->rowCount() === 1;
}

/** @param array<mixed> $payload */
function revokeProtected(\PDO $connection, array $payload, string $table, string $roleCode): bool
{
    if (!in_array($table, ['platform_role_assignments', 'workspace_role_assignments'], true)) {
        throw new RuntimeException('Authorization mutation worker table is invalid.');
    }
    $assignmentId = positiveInt($payload, 'assignment_id');
    $workspaceId = $table === 'workspace_role_assignments' ? positiveInt($payload, 'workspace_id') : null;
    $workspaceWhere = $workspaceId === null ? '' : ' AND assignment.workspace_id = :workspace_id';
    $lock = $connection->prepare('SELECT assignment.id FROM ' . $table . ' assignment '
        . 'INNER JOIN authorization_roles role_definition ON role_definition.id = assignment.role_id '
        . "AND role_definition.status = 'ACTIVE' WHERE role_definition.code = :role "
        . "AND assignment.status = 'ACTIVE'" . $workspaceWhere . ' ORDER BY assignment.id FOR UPDATE');
    $lock->bindValue(':role', $roleCode);
    if ($workspaceId !== null) {
        $lock->bindValue(':workspace_id', $workspaceId, \PDO::PARAM_INT);
    }
    $lock->execute();
    if (count($lock->fetchAll(\PDO::FETCH_COLUMN)) <= 1) {
        return false;
    }
    $update = $connection->prepare('UPDATE ' . $table . " SET status = 'REVOKED', version = version + 1, "
        . "revoked_by_kind = 'SYSTEM', revocation_reason_code = 'SECURITY_RESPONSE', "
        . "revoked_at = UTC_TIMESTAMP(6), updated_at = UTC_TIMESTAMP(6) WHERE id = :id AND status = 'ACTIVE'");
    $update->execute([':id' => $assignmentId]);

    return $update->rowCount() === 1;
}

/** @param array<mixed> $payload */
function revokePlatformAuthorityDelayed(\PDO $connection, array $payload): bool
{
    $assignmentId = positiveInt($payload, 'assignment_id');
    $delayMicroseconds = positiveInt($payload, 'delay_microseconds');
    $update = $connection->prepare("UPDATE platform_role_assignments SET status = 'REVOKED', "
        . "version = version + 1, revoked_by_kind = 'SYSTEM', revocation_reason_code = 'SECURITY_RESPONSE', "
        . "revoked_at = UTC_TIMESTAMP(6), updated_at = UTC_TIMESTAMP(6) WHERE id = :id AND status = 'ACTIVE'");
    $update->execute([':id' => $assignmentId]);
    if ($update->rowCount() !== 1) {
        return false;
    }
    echo "locked\n";
    flush();
    usleep($delayMicroseconds);

    return true;
}

/** @param array<mixed> $payload */
function positiveInt(array $payload, string $key): int
{
    $value = $payload[$key] ?? null;
    if (!is_int($value) || $value < 1) {
        throw new RuntimeException('Authorization mutation worker identifier is invalid.');
    }

    return $value;
}
