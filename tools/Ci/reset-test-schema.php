<?php

declare(strict_types=1);

$environment = getenv('APP_ENV');
$host = getenv('QMDB_TEST_DB_HOST');
$port = getenv('QMDB_TEST_DB_PORT');
$database = getenv('QMDB_TEST_DB_NAME');
$username = getenv('QMDB_TEST_DB_SCHEMA_USERNAME');
$password = getenv('QMDB_TEST_DB_SCHEMA_PASSWORD');

if (
    $environment !== 'test'
    || !is_string($host) || $host === ''
    || !is_string($port) || preg_match('/\A[1-9][0-9]{0,4}\z/', $port) !== 1
    || !is_string($database) || preg_match('/\A[A-Za-z0-9_]+(?:_test|_ci)\z/', $database) !== 1
    || !is_string($username) || $username === ''
    || !is_string($password) || $password === ''
) {
    fwrite(STDERR, "Test schema reset refused unsafe or incomplete environment.\n");
    exit(1);
}

try {
    $connection = new \PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database),
        $username,
        $password,
        [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_STRINGIFY_FETCHES => false,
        ],
    );
    $statement = $connection->prepare(
        "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = :schema ORDER BY TABLE_NAME",
    );
    $statement->execute([':schema' => $database]);
    $tables = $statement->fetchAll(\PDO::FETCH_COLUMN);
    $connection->exec('SET SESSION FOREIGN_KEY_CHECKS = 0');
    try {
        foreach ($tables as $table) {
            if (!is_string($table) || preg_match('/\A[A-Za-z0-9_]+\z/', $table) !== 1) {
                throw new \UnexpectedValueException('Test schema contains an unsafe table identifier.');
            }
            $connection->exec('DROP TABLE IF EXISTS `' . $table . '`');
        }
    } finally {
        $connection->exec('SET SESSION FOREIGN_KEY_CHECKS = 1');
    }
    fwrite(STDOUT, sprintf("Test schema reset: PASS (%d tables removed)\n", count($tables)));
    exit(0);
} catch (\Throwable $exception) {
    fwrite(STDERR, "Test schema reset: FAIL\n");
    exit(1);
}
