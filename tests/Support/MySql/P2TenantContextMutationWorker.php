<?php

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols -- isolated concurrent test worker

$payload = json_decode((string)stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (!is_array($payload) || !is_string($payload['operation'] ?? null)) {
    throw new RuntimeException('Tenant context mutation worker payload is invalid.');
}
$dsn = 'mysql:host=' . (string)getenv('QMDB_TEST_DB_HOST')
    . ';port=' . (string)getenv('QMDB_TEST_DB_PORT')
    . ';dbname=' . (string)getenv('QMDB_TEST_DB_NAME')
    . ';charset=utf8mb4';
$connection = new PDO(
    $dsn,
    (string)getenv('QMDB_TEST_DB_USERNAME'),
    (string)getenv('QMDB_TEST_DB_PASSWORD'),
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ],
);
$connection->exec("SET SESSION time_zone = '+00:00'");
$startAt = positiveInt($payload, 'start_at_microseconds');
$remaining = $startAt - (int)floor(microtime(true) * 1_000_000);
if ($remaining > 0) {
    usleep($remaining);
}

$status = 'unchanged';
try {
    $connection->beginTransaction();
    $status = match ($payload['operation']) {
        'select' => selectContext($connection, $payload),
        'clear' => clearContext($connection, $payload),
        'revoke_membership' => updateStatus($connection, $payload, 'workspace_memberships', 'REVOKED'),
        'suspend_workspace' => updateStatus($connection, $payload, 'workspaces', 'SUSPENDED'),
        'rotate_token' => rotateToken($connection, $payload),
        default => throw new RuntimeException('Tenant context mutation worker operation is invalid.'),
    };
    $connection->commit();
} catch (Throwable $exception) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
    throw $exception;
}

echo json_encode(['status' => $status], JSON_THROW_ON_ERROR);

/** @param array<mixed> $payload */
function selectContext(PDO $connection, array $payload): string
{
    $sessionId = positiveInt($payload, 'session_id');
    $accountId = positiveInt($payload, 'account_id');
    $expectedVersion = positiveInt($payload, 'expected_version');
    $workspaceId = positiveInt($payload, 'workspace_id');
    $membershipId = positiveInt($payload, 'membership_id');
    if (!lockExpectedSession($connection, $sessionId, $accountId, $expectedVersion)) {
        return 'stale';
    }
    $candidate = $connection->prepare('SELECT m.id FROM workspace_memberships m '
        . 'INNER JOIN workspaces w ON w.id = m.workspace_id '
        . "WHERE m.id = :membership_id AND m.workspace_id = :workspace_id "
        . "AND m.user_account_id = :account_id AND m.status_code = 'ACTIVE' "
        . "AND w.status_code = 'ACTIVE' LIMIT 1 FOR UPDATE");
    $candidate->execute([
        ':membership_id' => $membershipId,
        ':workspace_id' => $workspaceId,
        ':account_id' => $accountId,
    ]);
    if ($candidate->fetchColumn() === false) {
        return 'unavailable';
    }
    $update = $connection->prepare('UPDATE user_sessions SET selected_workspace_id = :workspace_id, '
        . 'selected_membership_id = :membership_id, tenant_context_selected_at = UTC_TIMESTAMP(6), '
        . 'tenant_context_version = tenant_context_version + 1, updated_at = UTC_TIMESTAMP(6) '
        . "WHERE id = :session_id AND account_id = :account_id AND status = 'ACTIVE' "
        . 'AND tenant_context_version = :expected_version');
    $update->execute([
        ':workspace_id' => $workspaceId,
        ':membership_id' => $membershipId,
        ':session_id' => $sessionId,
        ':account_id' => $accountId,
        ':expected_version' => $expectedVersion,
    ]);

    return $update->rowCount() === 1 ? 'selected' : 'stale';
}

/** @param array<mixed> $payload */
function clearContext(PDO $connection, array $payload): string
{
    $sessionId = positiveInt($payload, 'session_id');
    $accountId = positiveInt($payload, 'account_id');
    $expectedVersion = positiveInt($payload, 'expected_version');
    if (!lockExpectedSession($connection, $sessionId, $accountId, $expectedVersion)) {
        return 'stale';
    }
    $update = $connection->prepare('UPDATE user_sessions SET selected_workspace_id = NULL, '
        . 'selected_membership_id = NULL, tenant_context_selected_at = NULL, '
        . 'tenant_context_version = tenant_context_version + 1, updated_at = UTC_TIMESTAMP(6) '
        . "WHERE id = :session_id AND account_id = :account_id AND status = 'ACTIVE' "
        . 'AND tenant_context_version = :expected_version');
    $update->execute([
        ':session_id' => $sessionId,
        ':account_id' => $accountId,
        ':expected_version' => $expectedVersion,
    ]);

    return $update->rowCount() === 1 ? 'cleared' : 'stale';
}

function lockExpectedSession(PDO $connection, int $sessionId, int $accountId, int $expectedVersion): bool
{
    $lock = $connection->prepare("SELECT tenant_context_version FROM user_sessions WHERE id = :session_id "
        . "AND account_id = :account_id AND status = 'ACTIVE' LIMIT 1 FOR UPDATE");
    $lock->execute([':session_id' => $sessionId, ':account_id' => $accountId]);

    return (int)$lock->fetchColumn() === $expectedVersion;
}

/** @param array<mixed> $payload */
function updateStatus(PDO $connection, array $payload, string $table, string $status): string
{
    if (!in_array($table, ['workspace_memberships', 'workspaces'], true)) {
        throw new RuntimeException('Tenant context mutation worker table is invalid.');
    }
    $id = positiveInt($payload, 'record_id');
    $statement = $connection->prepare('UPDATE ' . $table . ' SET status_code = :status, '
        . 'version = version + 1, updated_at = UTC_TIMESTAMP(6) WHERE id = :id');
    $statement->execute([':status' => $status, ':id' => $id]);

    return $statement->rowCount() === 1 ? 'status_changed' : 'unchanged';
}

/** @param array<mixed> $payload */
function rotateToken(PDO $connection, array $payload): string
{
    $sessionId = positiveInt($payload, 'session_id');
    $statement = $connection->prepare('UPDATE user_sessions SET previous_token_hash = current_token_hash, '
        . 'current_token_hash = :token_hash, '
        . 'previous_token_expires_at = DATE_ADD(UTC_TIMESTAMP(6), INTERVAL 1 MINUTE), '
        . 'rotated_at = UTC_TIMESTAMP(6), version = version + 1, updated_at = UTC_TIMESTAMP(6) '
        . 'WHERE id = :session_id AND status = :status');
    $statement->execute([
        ':token_hash' => hash('sha256', 'rotated-' . $sessionId, true),
        ':session_id' => $sessionId,
        ':status' => 'ACTIVE',
    ]);

    return $statement->rowCount() === 1 ? 'rotated' : 'unchanged';
}

/** @param array<mixed> $payload */
function positiveInt(array $payload, string $key): int
{
    $value = $payload[$key] ?? null;
    if (!is_int($value) || $value < 1) {
        throw new RuntimeException('Tenant context mutation worker identifier is invalid.');
    }

    return $value;
}
