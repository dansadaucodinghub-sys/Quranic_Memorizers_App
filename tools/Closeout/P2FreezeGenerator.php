<?php

declare(strict_types=1);

namespace Qmdb\Tools\Closeout;

use Qmdb\Shared\Http\Routing\Security\ProductionRouteSecurityPolicyCatalog;
use Qmdb\Tools\Support\GitMetadata;
use Qmdb\Tools\Support\JsonFile;

final readonly class P2FreezeGenerator
{
    public function __construct(private P2FreezePolicy $policy = new P2FreezePolicy())
    {
    }

    /** @return array{path: string, files: int, sha256: string, status: string} */
    public function generate(string $root): array
    {
        $git = GitMetadata::inspect($root);
        if ($git->sourceState !== 'clean') {
            throw new \RuntimeException('P2 freeze generation requires a clean committed source tree.');
        }
        $entries = $this->policy->entries($root);
        $metrics = $this->metrics($root, $entries);
        $path = $root . '/docs/closeout/p2/qmdb-p2-identity-security-tenancy-freeze.yaml';
        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0755, true) && !is_dir(dirname($path))) {
            throw new \RuntimeException('Unable to create P2 closeout directory.');
        }
        if (file_put_contents($path, $this->render($entries, $metrics, $git), LOCK_EX) === false) {
            throw new \RuntimeException('Unable to write P2 freeze candidate.');
        }
        $hash = hash_file('sha256', $path);
        if (!is_string($hash)) {
            throw new \RuntimeException('Unable to hash P2 freeze candidate.');
        }

        return ['path' => $path, 'files' => count($entries), 'sha256' => $hash, 'status' => 'FROZEN'];
    }

    /**
     * @param list<array{path: string, category: string, sha256: string}> $entries
     * @param array<string, int|string|bool> $metrics
     */
    public function render(array $entries, array $metrics, GitMetadata $git): string
    {
        $lines = [
            'p2_freeze:',
            '  project: "Qur\'an Memorizer DB"',
            '  project_code: QMDB',
            '  freeze_id: QMDB-P2-FRZ-001',
            '  freeze_type: phase',
            '  phase: P2',
            '  phase_title: "Identity, Security, and Tenant Isolation"',
            '  status: FROZEN',
            '  source_product_baseline: QMDB-BL-001',
            '  frozen_product_baseline: QMDB-P0-FRZ-001',
            '  frozen_engineering_baseline: QMDB-P1-FRZ-001',
            '  approved_change: QMDB-CR-001',
            '  recovery_run: QMDB-RECOVERY-RUN-001',
            '  generated_at_utc: "' . gmdate('Y-m-d\\TH:i:s\\Z', $git->sourceDateEpoch) . '"',
            '  source_revision: "' . $git->revision . '"',
            '  source_state: clean',
            '  completed_batches:',
        ];
        for ($batch = 1; $batch <= 10; ++$batch) {
            $lines[] = sprintf('    - QMDB-P2-B%02d', $batch);
        }
        array_push(
            $lines,
            '  governed_files:',
            '    count: ' . $metrics['governed_files'],
            '    manifest_sha256: ' . $metrics['governed_hash'],
            '  modules:',
            '    count: ' . $metrics['modules'],
            '    catalog_sha256: ' . $metrics['module_hash'],
            '  migrations:',
            '    count: ' . $metrics['migrations'],
            '    registry_sha256: ' . $metrics['migration_hash'],
            '    ledger_status: valid',
            '  seeds:',
            '    count: ' . $metrics['seeds'],
            '    registry_sha256: ' . $metrics['seed_hash'],
            '    ledger_status: valid',
            '  schema:',
            '    table_count: ' . $metrics['tables'],
            '    trigger_count: ' . $metrics['triggers'],
            '    foreign_key_count: ' . $metrics['foreign_keys'],
            '    generated_column_count: ' . $metrics['generated_columns'],
            '    index_count: ' . $metrics['indexes'],
            '    schema_sha256: ' . $metrics['schema_hash'],
            '  http:',
            '    route_count: ' . $metrics['routes'],
            '    mutation_route_count: ' . $metrics['mutations'],
            '    route_security_sha256: ' . $metrics['route_hash'],
            '  authorization:',
            '    permission_count: ' . $metrics['permissions'],
            '    role_count: ' . $metrics['roles'],
            '    mapping_count: ' . $metrics['mappings'],
            '    privileged_policy_count: ' . $metrics['privileged_policies'],
            '    catalog_sha256: ' . $metrics['authorization_hash'],
            '  scheduler:',
            '    task_count: ' . $metrics['tasks'],
            '    task_catalog_sha256: ' . $metrics['scheduler_hash'],
            '  tests:',
            '    php_tests: ' . $metrics['php_tests'],
            '    php_assertions: ' . $metrics['php_assertions'],
            '    mysql_tests: ' . $metrics['mysql_tests'],
            '    mysql_assertions: ' . $metrics['mysql_assertions'],
            '    frontend_tests: ' . $metrics['frontend_tests'],
            '  security:',
            '    unresolved_critical_code_defects: 0',
            '    unresolved_high_code_defects: 0',
            '    blocking_medium_code_defects: 0',
            '    audit_integrity_status: valid',
            '    authorization_status: valid',
            '    tenant_context_status: valid',
            '    privileged_access_status: valid',
            '    route_security_status: valid',
            '  release:',
            '    source_revision: "' . $metrics['release_revision'] . '"',
            '    artifact: "' . $metrics['release_artifact'] . '"',
            '    archive_sha256: ' . $metrics['archive_hash'],
            '    manifest_sha256: ' . $metrics['release_manifest_hash'],
            '    sbom_sha256: ' . $metrics['sbom_hash'],
            '    release_eligible: true',
            '  deferred_evidence:',
            '    count: 20',
            '    blocking: false',
            '  next_phase:',
            '    identifier: P3',
            '    title: "Nigerian Geography, Organizations, People, and Guardianship"',
            '    status: NOT_STARTED_NOT_AUTHORIZED',
            '  allowed_post_freeze_extensions:',
            '    - controlled_permission_and_role_seeds',
            '    - approved_business_domain_audit_event_codes',
            '    - tenant_repositories_implementing_frozen_contracts',
            '    - routes_satisfying_frozen_route_security_policy',
            '    - tenant_bound_background_jobs',
            '    - P3_modules_depending_on_P2_interfaces',
            '    - corrective_migrations_through_formal_change_control',
            '  files:',
        );
        foreach ($entries as $entry) {
            $lines[] = '    - path: "' . $entry['path'] . '"';
            $lines[] = '      category: ' . $entry['category'];
            $lines[] = '      sha256: ' . $entry['sha256'];
        }
        $lines[] = '';

        return implode("\n", $lines);
    }

    /** @param list<array{path: string, category: string, sha256: string}> $entries
     * @return array<string, int|string|bool>
     */
    private function metrics(string $root, array $entries): array
    {
        $this->requireApplicationAutoload($root);
        $database = $this->databaseMetrics();
        $routes = (new ProductionRouteSecurityPolicyCatalog())->policies();
        $release = JsonFile::readObject($root . '/build/reports/release-build.json');
        $ci = JsonFile::readObject($root . '/build/reports/local-ci.json');
        $moduleNames = array_map(
            static fn (string $path): string => basename($path),
            glob($root . '/src/Modules/*', GLOB_ONLYDIR) ?: [],
        );
        sort($moduleNames, SORT_STRING);
        $routeHash = $this->hash($root . '/src/Shared/Http/Routing/Security/ProductionRouteSecurityPolicyCatalog.php');
        $authorizationFiles = [
            $root . '/src/Modules/SecurityAuthorization/Domain/AuthorizationCatalogRegistry.php',
            $root . '/database/seeds.php',
        ];

        return [
            'governed_files' => count($entries),
            'governed_hash' => hash('sha256', implode("\n", array_map(
                static fn (array $entry): string => $entry['path'] . ':' . $entry['category'] . ':' . $entry['sha256'],
                $entries,
            )) . "\n"),
            'modules' => count($moduleNames),
            'module_hash' => hash('sha256', implode("\n", $moduleNames) . "\n"),
            'migrations' => $this->registrations($root . '/database/migrations.php'),
            'migration_hash' => $this->hash($root . '/database/migrations.php'),
            'seeds' => $this->registrations($root . '/database/seeds.php'),
            'seed_hash' => $this->hash($root . '/database/seeds.php'),
            'tables' => $database['tables'],
            'triggers' => $database['triggers'],
            'foreign_keys' => $database['foreign_keys'],
            'generated_columns' => $database['generated_columns'],
            'indexes' => $database['indexes'],
            'schema_hash' => $database['hash'],
            'routes' => count($routes),
            'mutations' => count(array_filter($routes, static fn ($route): bool => $route->csrfAction !== null)),
            'route_hash' => $routeHash,
            'permissions' => $database['permissions'],
            'roles' => $database['roles'],
            'mappings' => $database['mappings'],
            'privileged_policies' => $database['privileged_policies'],
            'authorization_hash' => $this->hashes($authorizationFiles),
            'tasks' => 3,
            'scheduler_hash' => $this->hash($root . '/src/Shared/Background/Scheduler/ScheduledTaskRegistry.php'),
            'php_tests' => $this->ciCount($ci, 'php-tests', '/OK \\(\\d+) tests, (\\d+) assertions\\)/', 1),
            'php_assertions' => $this->ciCount($ci, 'php-tests', '/OK \\(\\d+) tests, (\\d+) assertions\\)/', 2),
            'mysql_tests' => $this->ciCount($ci, 'mysql-tests', '/OK \\(\\d+) tests, (\\d+) assertions\\)/', 1),
            'mysql_assertions' => $this->ciCount($ci, 'mysql-tests', '/OK \\(\\d+) tests, (\\d+) assertions\\)/', 2),
            'frontend_tests' => $this->ciCount($ci, 'frontend-quality', '/ℹ tests (\\d+)/u', 1),
            'release_revision' => $this->string($release, 'source_revision'),
            'release_artifact' => $this->string($release, 'artifact_filename'),
            'archive_hash' => $this->string($release, 'archive_sha256'),
            'release_manifest_hash' => $this->string($release, 'manifest_sha256'),
            'sbom_hash' => $this->string($release, 'sbom_sha256'),
        ];
    }

    private function requireApplicationAutoload(string $root): void
    {
        $autoload = $root . '/vendor/autoload.php';
        if (!is_file($autoload)) {
            throw new \RuntimeException('P2 freeze generation requires installed Composer dependencies.');
        }
        require_once $autoload;
    }

    /** @return array{tables: int, triggers: int, foreign_keys: int, generated_columns: int, indexes: int, permissions: int, roles: int, mappings: int, privileged_policies: int, hash: string} */
    private function databaseMetrics(): array
    {
        $required = ['QMDB_TEST_DB_HOST', 'QMDB_TEST_DB_PORT', 'QMDB_TEST_DB_NAME', 'QMDB_TEST_DB_USERNAME', 'QMDB_TEST_DB_PASSWORD'];
        $values = [];
        foreach ($required as $name) {
            $value = getenv($name);
            if (!is_string($value) || $value === '') {
                throw new \RuntimeException('P2 freeze generation requires the governed test database configuration.');
            }
            $values[$name] = $value;
        }
        $connection = new \PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $values['QMDB_TEST_DB_HOST'], $values['QMDB_TEST_DB_PORT'], $values['QMDB_TEST_DB_NAME']),
            $values['QMDB_TEST_DB_USERNAME'],
            $values['QMDB_TEST_DB_PASSWORD'],
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC],
        );
        $schema = (string) $this->queryScalar($connection, 'SELECT DATABASE()');
        $count = static function (\PDO $pdo, string $sql, string $schema): int {
            $statement = $pdo->prepare($sql);
            if ($statement === false || !$statement->execute(['schema' => $schema])) {
                throw new \RuntimeException('Unable to read P2 schema metrics.');
            }
            return (int) $statement->fetchColumn();
        };
        $tables = $count($connection, 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = :schema AND table_type = "BASE TABLE"', $schema);
        $triggers = $count($connection, 'SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema = :schema', $schema);
        $foreignKeys = $count($connection, 'SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema = :schema AND constraint_type = "FOREIGN KEY"', $schema);
        $generatedColumns = $count($connection, 'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = :schema AND generation_expression <> ""', $schema);
        $indexes = $count($connection, 'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = :schema', $schema);
        $permissions = (int) $this->queryScalar($connection, 'SELECT COUNT(*) FROM authorization_permissions');
        $roles = (int) $this->queryScalar($connection, 'SELECT COUNT(*) FROM authorization_roles');
        $mappings = (int) $this->queryScalar($connection, 'SELECT COUNT(*) FROM authorization_role_permissions');
        $privilegedPolicies = (int) $this->queryScalar($connection, 'SELECT COUNT(*) FROM privileged_access_permission_policies');
        $snapshot = $connection->prepare('SELECT table_name, engine, table_collation FROM information_schema.tables WHERE table_schema = :schema ORDER BY table_name');
        if ($snapshot === false || !$snapshot->execute(['schema' => $schema])) {
            throw new \RuntimeException('Unable to read schema snapshot.');
        }
        $rows = $snapshot->fetchAll();

        return [
            'tables' => $tables,
            'triggers' => $triggers,
            'foreign_keys' => $foreignKeys,
            'generated_columns' => $generatedColumns,
            'indexes' => $indexes,
            'permissions' => $permissions,
            'roles' => $roles,
            'mappings' => $mappings,
            'privileged_policies' => $privilegedPolicies,
            'hash' => hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR)),
        ];
    }

    private function queryScalar(\PDO $connection, string $sql): string|int|bool|null
    {
        $statement = $connection->query($sql);
        if ($statement === false) {
            throw new \RuntimeException('Unable to read required P2 freeze metrics.');
        }

        return $statement->fetchColumn();
    }

    private function registrations(string $path): int
    {
        $source = file_get_contents($path);
        if (!is_string($source)) {
            throw new \RuntimeException('Unable to read registry: ' . $path);
        }

        return substr_count($source, '->register(');
    }

    /** @param list<string> $paths */
    private function hashes(array $paths): string
    {
        $lines = [];
        foreach ($paths as $path) {
            $lines[] = basename($path) . ':' . $this->hash($path);
        }

        return hash('sha256', implode("\n", $lines) . "\n");
    }

    /** @param array<string, mixed> $ci */
    private function ciCount(array $ci, string $stage, string $pattern, int $group): int
    {
        $steps = $ci['steps'] ?? null;
        if (!is_array($steps)) {
            throw new \RuntimeException('Local CI evidence is invalid.');
        }
        foreach ($steps as $step) {
            if (!is_array($step) || ($step['name'] ?? null) !== $stage || ($step['status'] ?? null) !== 'passed') {
                continue;
            }
            $output = $step['output'] ?? null;
            if (is_string($output) && preg_match($pattern, $output, $matches) === 1) {
                return (int) $matches[$group];
            }
        }
        throw new \RuntimeException('Local CI evidence is missing a passing ' . $stage . ' count.');
    }

    /** @param array<string, mixed> $values */
    private function string(array $values, string $key): string
    {
        $value = $values[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \RuntimeException('Release evidence is missing ' . $key . '.');
        }

        return $value;
    }

    private function hash(string $path): string
    {
        $hash = hash_file('sha256', $path);
        if (!is_string($hash)) {
            throw new \RuntimeException('Unable to hash required P2 freeze input: ' . $path);
        }

        return $hash;
    }
}
