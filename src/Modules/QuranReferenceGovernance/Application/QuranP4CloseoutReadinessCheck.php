<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Application;

use PDO;
use PDOStatement;
use Qmdb\Modules\QuranReferenceGovernance\Configuration\QuranP4RequirementRegistry;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use RuntimeException;

/**
 * Read-only executable gate for the P4 runtime. It deliberately verifies the
 * active database projection and checked-in source locks without importing,
 * changing lifecycle state, or exposing Qur'an source text.
 */
final readonly class QuranP4CloseoutReadinessCheck
{
    private const array REQUIRED_TABLES = [
        'quran_reference_sources',
        'quran_source_artifacts',
        'quran_reference_releases',
        'quran_release_artifacts',
        'quran_release_manifests',
        'quran_release_validations',
        'quran_release_events',
        'quran_release_content_summaries',
        'quran_surahs',
        'quran_ayahs',
        'quran_partitions',
        'quran_sajdah_markers',
        'quran_search_corpora',
        'quran_ayah_search_texts',
        'quran_search_corpus_validations',
        'quran_search_corpus_events',
    ];

    private const array PUBLIC_ROUTES = [
        'quran.public.home',
        'quran.public.surahs',
        'quran.public.surah',
        'quran.public.ayah',
        'quran.public.partition',
        'quran.public.sajdahs',
        'quran.public.search',
    ];

    private const array PRIVATE_ROUTES = [
        'platform.quran.releases.index',
        'platform.quran.releases.detail',
        'platform.quran.releases.stage',
        'platform.quran.releases.validate',
        'platform.quran.releases.approve',
        'platform.quran.releases.activate',
        'platform.quran.releases.reject',
        'platform.quran.search_corpus',
        'platform.quran.search_corpus.validate',
    ];

    public function __construct(
        private string $projectRoot,
        private DatabaseConnectionProvider $connections,
        private QuranP4RequirementRegistry $requirements,
    ) {
    }

    /** @return array{requirements:int,sources:int,canonical_ayahs:int,search_ayahs:int,public_routes:int,private_routes:int} */
    public function verify(): array
    {
        $this->requirements->assertValid();
        $this->verifyArtifactLocks();
        $this->verifyRoutes();
        $this->verifyNoCompetitionSource();

        $pdo = $this->connections->connection();
        $this->verifyTables($pdo);
        $sources = $this->verifySources($pdo);
        [$canonicalAyahs, $searchAyahs] = $this->verifyActiveContent($pdo);

        return [
            'requirements' => count($this->requirements->entries()),
            'sources' => $sources,
            'canonical_ayahs' => $canonicalAyahs,
            'search_ayahs' => $searchAyahs,
            'public_routes' => count(self::PUBLIC_ROUTES),
            'private_routes' => count(self::PRIVATE_ROUTES),
        ];
    }

    private function verifyArtifactLocks(): void
    {
        $b02 = $this->readLock('resources/data/quran/tanzil/b02-source-artifacts.lock.json');
        $search = $this->readLock('resources/data/quran/tanzil/search-artifacts.lock.json');

        $this->assertLockHash($b02, false);
        $this->assertLockHash($search, true);
        $this->assertArtifacts($b02, ['TANZIL_UTHMANI_1_1', 'TANZIL_QURAN_METADATA_1_0']);
        $this->assertArtifacts($search, ['TANZIL_SIMPLE_CLEAN_1_1'], false);
    }

    /** @return array<mixed, mixed> */
    private function readLock(string $relativePath): array
    {
        $path = $this->projectRoot . '/' . $relativePath;
        $contents = file_get_contents($path);
        $lock = is_string($contents) ? json_decode($contents, true, 32, JSON_THROW_ON_ERROR) : null;
        if (!is_array($lock)) {
            throw new RuntimeException('A governed Qur’an artifact lock is invalid.');
        }
        foreach (array_keys($lock) as $key) {
            if (!is_string($key)) {
                throw new RuntimeException('A governed Qur’an artifact lock is invalid.');
            }
        }

        return $lock;
    }

    /** @param array<mixed, mixed> $lock */
    private function assertLockHash(array $lock, bool $pretty): void
    {
        $storedHash = $lock['lock_sha256'] ?? null;
        unset($lock['lock_sha256']);
        $canonical = $pretty
            ? json_encode($lock, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : $this->canonicalJson($lock);
        if (!is_string($storedHash) || !hash_equals($storedHash, hash('sha256', $canonical))) {
            throw new RuntimeException('A governed Qur’an artifact lock checksum differs.');
        }
    }

    /**
     * @param array<mixed, mixed> $lock
     * @param list<string> $expectedCodes
     */
    private function assertArtifacts(array $lock, array $expectedCodes, bool $exact = true): void
    {
        $artifacts = $lock['artifacts'] ?? null;
        if (!is_array($artifacts) || ($exact && count($artifacts) !== count($expectedCodes))) {
            throw new RuntimeException('A governed Qur’an artifact lock has an invalid scope.');
        }

        $codes = [];
        foreach ($artifacts as $artifact) {
            if (!is_array($artifact)) {
                throw new RuntimeException('A governed Qur’an artifact entry is invalid.');
            }
            $relativePath = $artifact['repository_relative_path'] ?? null;
            $checksum = $artifact['sha256'] ?? null;
            $code = $artifact['source_code'] ?? null;
            if (!is_string($relativePath) || !is_string($checksum) || !is_string($code) || !$this->isSafeRelativePath($relativePath)) {
                throw new RuntimeException('A governed Qur’an artifact path is invalid.');
            }
            $path = $this->projectRoot . '/' . $relativePath;
            if (!is_file($path) || !hash_equals($checksum, (string) hash_file('sha256', $path))) {
                throw new RuntimeException('A governed Qur’an source artifact checksum differs.');
            }
            $codes[] = $code;
        }

        if ($exact && $codes !== $expectedCodes) {
            throw new RuntimeException('A governed Qur’an artifact lock contains unexpected source codes.');
        }
        foreach ($expectedCodes as $expectedCode) {
            if (!in_array($expectedCode, $codes, true)) {
                throw new RuntimeException('A governed Qur’an artifact lock contains unexpected source codes.');
            }
        }
    }

    private function isSafeRelativePath(string $path): bool
    {
        return !str_contains($path, '..')
            && !str_starts_with($path, '/')
            && !str_starts_with($path, '\\')
            && !str_contains($path, ':');
    }

    private function verifyRoutes(): void
    {
        $routes = file_get_contents($this->projectRoot . '/routes/web.php');
        if (!is_string($routes)) {
            throw new RuntimeException('The application route registry is unavailable.');
        }

        foreach (self::PUBLIC_ROUTES as $route) {
            $getOnly = preg_match(
                "/new Route\\('" . preg_quote($route, '/') . "'\\s*,\\s*\\[HttpMethod::GET\\]/",
                $routes,
            );
            if (substr_count($routes, "'{$route}'") !== 1 || $getOnly !== 1) {
                throw new RuntimeException('A required public Qur’an route is missing or not GET-only.');
            }
        }
        foreach (self::PRIVATE_ROUTES as $route) {
            if (substr_count($routes, "'{$route}'") !== 1) {
                throw new RuntimeException('A required private Qur’an governance route is missing or duplicated.');
            }
        }
    }

    private function verifyNoCompetitionSource(): void
    {
        if (is_dir($this->projectRoot . '/src/Modules/Competition')) {
            throw new RuntimeException('Competition production source is prohibited during P4 closeout.');
        }
    }

    private function verifyTables(PDO $pdo): void
    {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table',
        );
        foreach (self::REQUIRED_TABLES as $table) {
            $statement->execute([':table' => $table]);
            if ((int) $statement->fetchColumn() !== 1) {
                throw new RuntimeException('A required Qur’an runtime table is unavailable.');
            }
        }
    }

    private function verifySources(PDO $pdo): int
    {
        $sources = $this->query($pdo,
            "SELECT source_code, source_version, content_role, runtime_download_allowed, status\n"
            . 'FROM quran_reference_sources ORDER BY source_code',
        )->fetchAll(PDO::FETCH_ASSOC);
        $expected = [
            'TANZIL_QURAN_METADATA_1_0' => ['1.0', 'STRUCTURAL_METADATA'],
            'TANZIL_SIMPLE_CLEAN_1_1' => ['1.1', 'SEARCH_TEXT'],
            'TANZIL_UTHMANI_1_1' => ['1.1', 'CANONICAL_TEXT'],
        ];
        if (count($sources) !== count($expected)) {
            throw new RuntimeException('The approved Qur’an source inventory is invalid.');
        }
        foreach ($sources as $source) {
            if (!is_array($source)) {
                throw new RuntimeException('An approved Qur’an source definition is invalid.');
            }
            $code = $source['source_code'] ?? null;
            if (
                !is_string($code)
                || !isset($expected[$code])
                || [$source['source_version'] ?? null, $source['content_role'] ?? null] !== $expected[$code]
                || !in_array($source['runtime_download_allowed'] ?? null, [0, '0'], true)
                || ($source['status'] ?? null) !== 'APPROVED'
            ) {
                throw new RuntimeException('An approved Qur’an source definition is invalid.');
            }
        }

        return count($sources);
    }

    /** @return array{0:int,1:int} */
    private function verifyActiveContent(PDO $pdo): array
    {
        $release = $this->query($pdo,
            "SELECT r.id, s.ayah_count FROM quran_reference_releases r\n"
            . "INNER JOIN quran_release_content_summaries s ON s.release_id = r.id\n"
            . "WHERE r.status = 'ACTIVE'",
        )->fetchAll(PDO::FETCH_ASSOC);
        $releaseRow = $release[0] ?? null;
        if (!is_array($releaseRow) || count($release) !== 1) {
            throw new RuntimeException('P4 requires exactly one active canonical release.');
        }
        $releaseId = $this->requiredInt($releaseRow, 'id');
        $releaseAyahCount = $this->requiredInt($releaseRow, 'ayah_count');
        $canonicalAyahs = $this->count($pdo, 'SELECT COUNT(*) FROM quran_ayahs WHERE release_id = :release', [':release' => $releaseId]);
        if ($canonicalAyahs !== $releaseAyahCount) {
            throw new RuntimeException('The active canonical release summary is inconsistent.');
        }

        $corpora = $pdo->prepare(
            "SELECT id, ayah_count FROM quran_search_corpora WHERE canonical_release_id = :release AND status = 'ACTIVE'",
        );
        $corpora->execute([':release' => $releaseId]);
        $corpus = $corpora->fetchAll(PDO::FETCH_ASSOC);
        $corpusRow = $corpus[0] ?? null;
        if (!is_array($corpusRow) || count($corpus) !== 1) {
            throw new RuntimeException('P4 requires exactly one active search corpus for the active release.');
        }
        $searchAyahs = $this->count(
            $pdo,
            'SELECT COUNT(*) FROM quran_ayah_search_texts WHERE corpus_id = :corpus',
            [':corpus' => $this->requiredInt($corpusRow, 'id')],
        );
        if ($searchAyahs !== $canonicalAyahs || $searchAyahs !== $this->requiredInt($corpusRow, 'ayah_count')) {
            throw new RuntimeException('The active search corpus is not aligned one-to-one with canonical Ayahs.');
        }

        return [$canonicalAyahs, $searchAyahs];
    }

    /** @param array<string, int> $parameters */
    private function count(PDO $pdo, string $sql, array $parameters): int
    {
        $statement = $pdo->prepare($sql);
        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }

    private function query(PDO $pdo, string $sql): PDOStatement
    {
        $statement = $pdo->query($sql);
        if (!$statement instanceof PDOStatement) {
            throw new RuntimeException('P4 closeout verification query preparation failed.');
        }

        return $statement;
    }

    /** @param array<mixed, mixed> $row */
    private function requiredInt(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new RuntimeException('P4 closeout verification received an invalid database scalar.');
    }

    /** @param array<mixed, mixed> $value */
    private function canonicalJson(array $value): string
    {
        $this->sortKeys($value);

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** @param array<mixed, mixed> $value */
    private function sortKeys(array &$value): void
    {
        foreach ($value as &$entry) {
            if (is_array($entry)) {
                $this->sortKeys($entry);
            }
        }
        unset($entry);
        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }
    }
}
