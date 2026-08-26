<?php

declare(strict_types=1);

namespace Qmdb\Tests\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class ObservabilityArchitectureTest extends TestCase
{
    public function testMonologDoesNotCrossIntoDomainApplicationOrControllerCode(): void
    {
        foreach (
            [
            'src/Shared/Domain',
            'src/Shared/Application',
            'src/Shared/Http/Controller',
            ] as $directory
        ) {
            foreach ($this->phpFilesUnder($this->root() . '/' . $directory) as $path) {
                $source = $this->read($path);
                self::assertStringNotContainsString('Monolog\\', $source, $path);
                if (str_contains($directory, 'Domain')) {
                    self::assertStringNotContainsString('Psr\\Log\\', $source, $path);
                }
            }
        }
    }

    public function testOnlyTerminalInternalChannelCallsPhpErrorLog(): void
    {
        $approved = $this->root() . '/src/Shared/Observability/Error/InternalErrorChannel.php';

        foreach ($this->phpFilesUnder($this->root() . '/src') as $path) {
            $callsErrorLog = preg_match('/\berror_log\s*\(/', $this->read($path)) === 1;
            self::assertSame($path === $approved, $callsErrorLog, $path);
        }
    }

    public function testNoUnsafeDumpOrSensitiveHttpLoggingPatternExists(): void
    {
        foreach ($this->phpFilesUnder($this->root() . '/src') as $path) {
            $source = $this->read($path);
            self::assertDoesNotMatchRegularExpression(
                '/\b(?:var_dump|print_r|var_export)\s*\(/',
                $source,
                $path,
            );

            if (str_contains($path, '/Observability/') || str_contains($path, '/Http/Middleware/')) {
                self::assertDoesNotMatchRegularExpression(
                    '/get(?:ParsedBody|Body|UploadedFiles|CookieParams|QueryParams)\s*\(/',
                    $source,
                    $path,
                );
                self::assertDoesNotMatchRegularExpression(
                    '/getHeader(?:Line)?\s*\(\s*[\'\"](?:Authorization|Cookie)/i',
                    $source,
                    $path,
                );
            }
        }
    }

    public function testInboundCorrelationHeadersAreNeverRead(): void
    {
        $source = $this->read(
            $this->root() . '/src/Shared/Http/Middleware/CorrelationIdMiddleware.php',
        );

        self::assertStringNotContainsString('getHeader', $source);
        self::assertStringContainsString('CorrelationIdGenerator', $source);
        self::assertStringContainsString("withHeader('X-Request-ID'", $source);
    }

    public function testSecurityHeadersAreOwnedByMiddlewareAndSapiBoundary(): void
    {
        foreach ($this->phpFilesUnder($this->root() . '/src/Shared/Http/Controller') as $path) {
            $source = $this->read($path);
            foreach (
                [
                'Content-Security-Policy',
                'Strict-Transport-Security',
                'X-Frame-Options',
                'Permissions-Policy',
                ] as $header
            ) {
                self::assertStringNotContainsString($header, $source, $path);
            }
        }

        $middleware = $this->read(
            $this->root() . '/src/Shared/Http/Middleware/SecurityHeadersMiddleware.php',
        );
        self::assertStringContainsString('Content-Security-Policy', $middleware);
        self::assertStringContainsString('Strict-Transport-Security', $middleware);
        self::assertStringNotContainsString('X-Forwarded-Proto', $middleware);
        self::assertStringNotContainsString('Access-Control-Allow-Origin', $middleware);
    }

    public function testRuntimeDependenciesAreLimitedToApprovedLoggingAdditions(): void
    {
        $composer = json_decode(
            $this->read($this->root() . '/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($composer);
        $runtime = $composer['require'] ?? null;
        self::assertIsArray($runtime);

        self::assertSame('^3.10', $runtime['monolog/monolog'] ?? null);
        self::assertSame('^3.0', $runtime['psr/log'] ?? null);
        $forbidden = [
            'sentry/sentry',
            'open-telemetry/sdk',
            'elasticsearch/elasticsearch',
            'opensearch-project/opensearch-php',
        ];
        foreach ($forbidden as $package) {
            self::assertArrayNotHasKey($package, $runtime);
        }
    }

    public function testNoOperationalLogDatabaseAuditFrontendOrStreamingArtifactWasAdded(): void
    {
        foreach ($this->phpFilesUnder($this->root() . '/database') as $path) {
            $source = strtolower($this->read($path));
            self::assertStringNotContainsString('application_log', $source, $path);
            self::assertStringNotContainsString('business_audit', $source, $path);
        }

        foreach ($this->projectFiles() as $path) {
            if (preg_match('/\.(?:js|jsx|ts|tsx|vue)$/i', $path) !== 1) {
                continue;
            }
            self::assertStringContainsString('/public/assets/js/', $path, $path);
            self::assertStringEndsWith('.js', $path, $path);
        }

        self::assertDirectoryDoesNotExist($this->root() . '/src/Shared/Metrics');
        self::assertDirectoryDoesNotExist($this->root() . '/src/Shared/Tracing');
    }

    /** @return list<string> */
    private function phpFilesUnder(string $directory): array
    {
        return array_values(array_filter(
            $this->filesUnder($directory),
            static fn (string $path): bool => str_ends_with(strtolower($path), '.php'),
        ));
    }

    /** @return list<string> */
    private function projectFiles(): array
    {
        return array_merge(
            $this->filesUnder($this->root() . '/src'),
            $this->filesUnder($this->root() . '/public'),
            $this->filesUnder($this->root() . '/routes'),
        );
    }

    /** @return list<string> */
    private function filesUnder(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile()) {
                $files[] = str_replace('\\', '/', $file->getPathname());
            }
        }
        sort($files);

        return $files;
    }

    private function root(): string
    {
        return str_replace('\\', '/', dirname(__DIR__, 2));
    }

    private function read(string $path): string
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException(sprintf('Unable to read project file: %s', $path));
        }

        return $contents;
    }
}
