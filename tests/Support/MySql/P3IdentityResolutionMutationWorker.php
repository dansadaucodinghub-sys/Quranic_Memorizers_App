<?php

declare(strict_types=1);

$input = json_decode((string) stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (!is_array($input) || !is_int($input['pairing_id'] ?? null)) {
    throw new InvalidArgumentException('Identity-resolution mutation worker payload is invalid.');
}
$pdo = new \PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', getenv('QMDB_TEST_DB_HOST'), getenv('QMDB_TEST_DB_PORT'), getenv('QMDB_TEST_DB_NAME')),
    (string) getenv('QMDB_TEST_DB_USERNAME'),
    (string) getenv('QMDB_TEST_DB_PASSWORD'),
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_EMULATE_PREPARES => false],
);
$statement = $pdo->prepare("UPDATE people_profile_claim_pairings SET status = 'REVOKED',revoked_at = UTC_TIMESTAMP(6),version = version + 1,updated_at = UTC_TIMESTAMP(6) WHERE id = :id AND status = 'ACTIVE' AND version = 1");
$statement->execute(['id' => $input['pairing_id']]);
echo json_encode(['mutated' => $statement->rowCount() === 1], JSON_THROW_ON_ERROR);
