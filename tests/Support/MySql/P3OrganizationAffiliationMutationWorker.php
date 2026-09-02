<?php

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols -- isolated concurrent test worker

$payload = json_decode((string) stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (!is_array($payload) || !is_int($payload['workspace_id'] ?? null) || !is_string($payload['affiliation_id'] ?? null) || !is_int($payload['expected_version'] ?? null)) {
    throw new RuntimeException('Organization affiliation mutation worker payload is invalid.');
}
$connection = new PDO(
    'mysql:host=' . (string) getenv('QMDB_TEST_DB_HOST') . ';port=' . (string) getenv('QMDB_TEST_DB_PORT') . ';dbname=' . (string) getenv('QMDB_TEST_DB_NAME') . ';charset=utf8mb4',
    (string) getenv('QMDB_TEST_DB_USERNAME'),
    (string) getenv('QMDB_TEST_DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false],
);
$connection->exec("SET SESSION time_zone = '+00:00'");
$statement = $connection->prepare("UPDATE organization_affiliations SET version = version + 1, updated_at = UTC_TIMESTAMP(6) WHERE workspace_id = :workspace_id AND public_id = UUID_TO_BIN(:affiliation_id) AND status = 'ACTIVE' AND version = :expected_version");
$statement->execute($payload);
echo json_encode(['mutated' => $statement->rowCount() === 1], JSON_THROW_ON_ERROR);
