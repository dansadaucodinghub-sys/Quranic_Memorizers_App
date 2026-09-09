<?php

declare(strict_types=1);

namespace Qmdb\Tests\Tools\Security;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Qmdb\Tools\Security\TrivyDatabaseCache;
use Qmdb\Tools\Security\TrivyDatabaseException;
use Qmdb\Tools\Support\ProcessResult;

final class TrivyDatabaseCacheTest extends TestCase
{
    private string $root;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->root = rtrim(sys_get_temp_dir(), '\\/') . '/qmdb trivy cache ' . bin2hex(random_bytes(8));
        mkdir($this->root, 0700, true);
        file_put_contents($this->root . '/trivy', 'binary');
        $this->now = new \DateTimeImmutable('2026-09-09T11:00:00Z');
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);
    }

    public function testFreshCacheIsUsedWithoutNetworkAcquisition(): void
    {
        $cache = $this->root . '/cache';
        $this->writeCache($cache);
        $calls = 0;
        $database = new TrivyDatabaseCache(function () use (&$calls): ProcessResult {
            ++$calls;
            return new ProcessResult([], 1, '', 'unexpected');
        });

        $result = $database->ensureCurrent($this->root . '/trivy', $cache, $this->now);

        self::assertSame(0, $calls);
        self::assertSame(2, $result['version']);
    }

    public function testApprovedRepositoriesFallbackAndAtomicallyPublishValidatedDatabase(): void
    {
        $cache = $this->root . '/cache with spaces';
        $repositories = [];
        $database = new TrivyDatabaseCache(function (array $command) use (&$repositories): ProcessResult {
            $repositories[] = $command[6];
            if (count($repositories) === 1) {
                return new ProcessResult($command, 1, '', 'temporary registry outage');
            }
            $this->writeCache($command[4]);
            return new ProcessResult($command, 0, '', '');
        });

        $database->refresh($this->root . '/trivy', $cache, $this->now);

        self::assertSame(array_slice(TrivyDatabaseCache::REPOSITORIES, 0, 2), $repositories);
        self::assertFileExists($cache . '/db/trivy.db');
        self::assertFileExists($cache . '/db/metadata.json');
    }

    public function testInvalidCandidateNeverReplacesExistingDatabase(): void
    {
        $cache = $this->root . '/cache';
        $this->writeCache($cache, 'old-db');
        $database = new TrivyDatabaseCache(static fn (array $command): ProcessResult => new ProcessResult($command, 0, '', ''));

        try {
            $database->refresh($this->root . '/trivy', $cache, $this->now);
            self::fail('The invalid downloaded cache must fail closed.');
        } catch (TrivyDatabaseException $exception) {
            self::assertSame(TrivyDatabaseException::DATABASE_UNAVAILABLE, $exception->getCode());
        }
        self::assertSame('old-db', file_get_contents($cache . '/db/trivy.db'));
    }

    #[DataProvider('invalidCacheProvider')]
    public function testInvalidAndStaleCachesHaveDistinctFailureCategories(string $case, int $expectedCode): void
    {
        $cache = $this->root . '/' . $case;
        if ($case !== 'missing') {
            $this->writeCache($cache);
        }
        if ($case === 'empty') {
            file_put_contents($cache . '/db/trivy.db', '');
        } elseif ($case === 'malformed') {
            file_put_contents($cache . '/db/metadata.json', '{');
        } elseif ($case === 'schema') {
            $this->writeMetadata($cache, ['Version' => 1]);
        } elseif ($case === 'future') {
            $this->writeMetadata($cache, ['UpdatedAt' => '2026-09-10T11:00:00Z']);
        } elseif ($case === 'expired') {
            $this->writeMetadata($cache, ['NextUpdate' => '2026-09-09T10:59:59Z']);
        }
        $database = new TrivyDatabaseCache();

        try {
            $database->validate($cache, $this->now);
            self::fail('Invalid cache must fail closed: ' . $case);
        } catch (TrivyDatabaseException $exception) {
            self::assertSame($expectedCode, $exception->getCode());
        }
    }

    /** @return array<string, array{string, int}> */
    public static function invalidCacheProvider(): array
    {
        return [
            'missing' => ['missing', TrivyDatabaseException::CACHE_UNREADABLE],
            'empty database' => ['empty', TrivyDatabaseException::CACHE_UNREADABLE],
            'malformed metadata' => ['malformed', TrivyDatabaseException::METADATA_INVALID],
            'schema mismatch' => ['schema', TrivyDatabaseException::SCHEMA_MISMATCH],
            'future metadata' => ['future', TrivyDatabaseException::METADATA_INVALID],
            'expired metadata' => ['expired', TrivyDatabaseException::DATABASE_STALE],
        ];
    }

    private function writeCache(string $cache, string $contents = 'database'): void
    {
        mkdir($cache . '/db', 0700, true);
        file_put_contents($cache . '/db/trivy.db', $contents);
        $this->writeMetadata($cache);
    }

    /** @param array<string, int|string> $overrides */
    private function writeMetadata(string $cache, array $overrides = []): void
    {
        $metadata = array_merge([
            'Version' => 2,
            'UpdatedAt' => '2026-09-09T10:00:00Z',
            'NextUpdate' => '2026-09-10T10:00:00Z',
            'DownloadedAt' => '2026-09-09T10:30:00Z',
        ], $overrides);
        file_put_contents($cache . '/db/metadata.json', json_encode($metadata, JSON_THROW_ON_ERROR));
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            if (!$item instanceof \SplFileInfo) {
                continue;
            }
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($path);
    }
}
