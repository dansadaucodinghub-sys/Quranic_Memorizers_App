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

    /**
     * Remove child tables before their parents.  The test reset intentionally
     * keeps foreign-key enforcement enabled: disabling it can conceal a
     * migration defect and leaves an interrupted reset indistinguishable from
     * a clean schema.  Recompute the dependency graph after each drop because
     * MySQL removes the dropped table's constraint metadata atomically.
     *
     * @var array<string, true> $remainingTables
     */
    $remainingTables = [];
    foreach ($tables as $table) {
        if (!is_string($table) || preg_match('/\A[A-Za-z0-9_]+\z/', $table) !== 1) {
            throw new \UnexpectedValueException('Test schema contains an unsafe table identifier.');
        }

        $remainingTables[$table] = true;
    }

    $foreignKeyStatement = $connection->prepare(
        'SELECT TABLE_NAME, REFERENCED_TABLE_NAME
         FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = :table_schema
           AND REFERENCED_TABLE_SCHEMA = :referenced_schema
           AND REFERENCED_TABLE_NAME IS NOT NULL',
    );
    $foreignKeyConstraintStatement = $connection->prepare(
        'SELECT TABLE_NAME, CONSTRAINT_NAME
         FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = :table_schema
           AND REFERENCED_TABLE_SCHEMA = :referenced_schema
           AND REFERENCED_TABLE_NAME IS NOT NULL
         GROUP BY TABLE_NAME, CONSTRAINT_NAME
         ORDER BY TABLE_NAME, CONSTRAINT_NAME',
    );
    $dropped = 0;
    $releasedConstraints = 0;
    while ($remainingTables !== []) {
        $foreignKeyStatement->execute([
            ':table_schema' => $database,
            ':referenced_schema' => $database,
        ]);
        /** @var list<array{TABLE_NAME: string, REFERENCED_TABLE_NAME: string}> $foreignKeys */
        $foreignKeys = $foreignKeyStatement->fetchAll(\PDO::FETCH_ASSOC);
        $referencedTables = [];
        foreach ($foreignKeys as $foreignKey) {
            $childTable = $foreignKey['TABLE_NAME'];
            $parentTable = $foreignKey['REFERENCED_TABLE_NAME'];
            if (
                $childTable !== $parentTable
                && isset($remainingTables[$childTable], $remainingTables[$parentTable])
            ) {
                $referencedTables[$parentTable] = true;
            }
        }

        $dropCandidates = array_keys(array_diff_key($remainingTables, $referencedTables));
        sort($dropCandidates, SORT_STRING);
        if ($dropCandidates === []) {
            /*
             * The production schema intentionally has a small number of
             * nullable, forward-only reference cycles (for example a result
             * publication and its immutable package). MySQL cannot drop either
             * table while both constraints remain. Break only the constraints
             * inside the already validated test schema, then continue the
             * normal child-before-parent drop ordering. This keeps FK checks
             * enabled throughout and never touches a production schema.
             */
            $foreignKeyConstraintStatement->execute([
                ':table_schema' => $database,
                ':referenced_schema' => $database,
            ]);
            /** @var list<array{TABLE_NAME: string, CONSTRAINT_NAME: string}> $constraints */
            $constraints = $foreignKeyConstraintStatement->fetchAll(\PDO::FETCH_ASSOC);
            $released = false;
            foreach ($constraints as $constraint) {
                $table = $constraint['TABLE_NAME'];
                $name = $constraint['CONSTRAINT_NAME'];
                if (
                    !isset($remainingTables[$table])
                    || preg_match('/\A[A-Za-z0-9_]+\z/', $table) !== 1
                    || preg_match('/\A[A-Za-z0-9_]+\z/', $name) !== 1
                ) {
                    continue;
                }
                $connection->exec('ALTER TABLE `' . $table . '` DROP FOREIGN KEY `' . $name . '`');
                ++$releasedConstraints;
                $released = true;
            }
            if (!$released) {
                throw new \RuntimeException('Test schema reset cannot resolve its remaining foreign-key cycle.');
            }
            continue;
        }

        foreach ($dropCandidates as $table) {
            $connection->exec('DROP TABLE IF EXISTS `' . $table . '`');
            unset($remainingTables[$table]);
            $dropped++;
        }
    }
    fwrite(STDOUT, sprintf("Test schema reset: PASS (%d tables removed, %d cycle constraints released)\n", $dropped, $releasedConstraints));
    exit(0);
} catch (\Throwable $exception) {
    fwrite(STDERR, sprintf("Test schema reset: FAIL (%s)\n", $exception->getMessage()));
    exit(1);
}
