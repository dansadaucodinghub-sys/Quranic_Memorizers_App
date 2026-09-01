<?php

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols -- isolated concurrent test worker

$payload = json_decode((string) stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (!is_array($payload) || !is_int($payload['account_id'] ?? null) || !is_string($payload['registry_code'] ?? null)) {
    throw new RuntimeException('People mutation worker payload is invalid.');
}
$connection = new \PDO(
    'mysql:host=' . (string) getenv('QMDB_TEST_DB_HOST') . ';port=' . (string) getenv('QMDB_TEST_DB_PORT')
    . ';dbname=' . (string) getenv('QMDB_TEST_DB_NAME') . ';charset=utf8mb4',
    (string) getenv('QMDB_TEST_DB_USERNAME'),
    (string) getenv('QMDB_TEST_DB_PASSWORD'),
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_EMULATE_PREPARES => false],
);
$connection->exec("SET SESSION time_zone = '+00:00'");
$mutated = false;
try {
    $connection->beginTransaction();
    $person = $connection->prepare("INSERT INTO people_persons (public_id, registry_code, status, sex_classification, created_by_account_id, version, created_at, updated_at) VALUES (UUID_TO_BIN(UUID()), :registry_code, 'ACTIVE', 'NOT_RECORDED', :account_id, 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))");
    $person->execute([':registry_code' => $payload['registry_code'], ':account_id' => $payload['account_id']]);
    $link = $connection->prepare("INSERT INTO people_account_links (public_id, account_id, person_id, link_type, status, version, linked_at, created_at, updated_at) VALUES (UUID_TO_BIN(UUID()), :account_id, :person_id, 'SELF', 'ACTIVE', 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))");
    $link->execute([':account_id' => $payload['account_id'], ':person_id' => (int) $connection->lastInsertId()]);
    $connection->commit();
    $mutated = true;
} catch (\PDOException $exception) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
    if (($exception->errorInfo[0] ?? null) !== '23000') {
        throw $exception;
    }
}

echo json_encode(['mutated' => $mutated], JSON_THROW_ON_ERROR);
