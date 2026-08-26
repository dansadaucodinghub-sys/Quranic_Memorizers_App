<?php

declare(strict_types=1);

namespace Qmdb\Tools\Build;

use Qmdb\Tools\Sbom\SbomValidator;
use Qmdb\Tools\Security\SensitiveContentScanner;
use Qmdb\Tools\Support\FileSystem;
use Qmdb\Tools\Support\JsonFile;
use Qmdb\Tools\Support\ProcessRunner;

final class ReleaseArtifactVerifier
{
    public function __construct(
        private readonly ReleaseFilePolicy $policy = new ReleaseFilePolicy(),
        private readonly ProcessRunner $runner = new ProcessRunner(),
        private readonly TarArchiveReader $reader = new TarArchiveReader(),
    ) {
    }

    /** @return array<string, mixed> */
    public function verify(string $sourceRoot, string $archive): array
    {
        if (!$this->policy->validateArchiveFilename(basename($archive))) {
            throw new \RuntimeException('Release artifact filename is invalid.');
        }
        $checksums = dirname($archive) . '/SHA256SUMS';
        $expectedHash = $this->checksumFor($checksums, basename($archive));
        $actualHash = $this->hash($archive);
        if (!hash_equals($expectedHash, $actualHash)) {
            throw new \RuntimeException('Release archive checksum mismatch.');
        }
        $entries = $this->reader->entries($archive);
        foreach ($entries as $path => $entry) {
            if (($entry['mode'] & 0002) !== 0) {
                throw new \RuntimeException('World-writable archive entry: ' . $path);
            }
            if ($entry['type'] === 'file' && !$this->policy->isAllowed($path)) {
                throw new \RuntimeException('Unexpected artifact file: ' . $path);
            }
        }

        $temporaryBase = rtrim(sys_get_temp_dir(), '\\/') . '/qmdb-release-verify-' . bin2hex(random_bytes(8));
        try {
            $this->reader->extract($entries, $temporaryBase);
            $manifestData = JsonFile::readObject($temporaryBase . '/release-manifest.json');
            $manifest = new ReleaseManifest($manifestData);
            $this->verifyManifest($temporaryBase, $entries, $manifest);
            foreach ($this->policy->requiredFiles() as $required) {
                if (!is_file($temporaryBase . '/' . $required)) {
                    throw new \RuntimeException('Required artifact file is missing: ' . $required);
                }
            }
            $sbomPath = $temporaryBase . '/metadata/production-sbom.cdx.json';
            $sbom = (new SbomValidator())->validate($temporaryBase, $sbomPath);
            if (!$sbom->passed()) {
                throw new \RuntimeException('Artifact SBOM failed validation: ' . implode('; ', $sbom->errors()));
            }
            JsonFile::readObject($temporaryBase . '/metadata/runtime-licences.json');
            $findings = (new SensitiveContentScanner())->scanDirectory($temporaryBase, false);
            if ($findings !== []) {
                throw new \RuntimeException('Artifact secret scan found: ' . implode('; ', $findings));
            }
            $externalScans = $this->verifyExternalSecurityTools($sourceRoot, $temporaryBase);
            $platform = $this->runner->run(['composer', 'check-platform-reqs', '--no-dev'], $temporaryBase);
            if ($platform->exitCode !== 0) {
                throw new \RuntimeException('Artifact platform requirements failed: ' . $platform->output());
            }
            $phpCount = $this->verifyPhpSyntax($temporaryBase);
            $environment = $this->testEnvironment();
            $cli = $this->verifyCli($temporaryBase, $environment);
            $http = $this->verifyHttp($temporaryBase, $environment);
            $result = [
                'status' => 'verified',
                'artifact' => $archive,
                'archive_sha256' => $actualHash,
                'manifest_sha256' => $this->hash($temporaryBase . '/release-manifest.json'),
                'file_count' => count($manifest->files()) + 1,
                'php_files_linted' => $phpCount,
                'source_revision' => $manifestData['source_revision'] ?? null,
                'source_state' => $manifestData['source_state'] ?? null,
                'release_eligible' => $manifestData['release_eligible'] ?? false,
                'artifact_secret_scan' => 'pass',
                'external_security_scans' => $externalScans,
                'cli' => $cli,
                'http' => $http,
                'mysql_readiness_executed' => $this->hasMySqlEnvironment(),
            ];
            JsonFile::writeObject($sourceRoot . '/build/reports/release-verification.json', $result);
            return $result;
        } finally {
            $this->removeTemporary($temporaryBase);
        }
    }

    /** @param array<string, array{type: string, mode: int, content: string}> $archiveEntries */
    private function verifyManifest(string $root, array $archiveEntries, ReleaseManifest $manifest): void
    {
        $seen = [];
        foreach ($manifest->files() as $entry) {
            $path = $entry['path'];
            if (isset($seen[$path]) || $path === 'release-manifest.json') {
                throw new \RuntimeException('Release manifest contains invalid or duplicate path.');
            }
            $seen[$path] = true;
            $absolute = $root . '/' . $path;
            if (!is_file($absolute)) {
                throw new \RuntimeException('Manifest file is absent: ' . $path);
            }
            if (!hash_equals($entry['sha256'], $this->hash($absolute))) {
                throw new \RuntimeException('Manifest hash mismatch: ' . $path);
            }
            if ($entry['size'] !== filesize($absolute)) {
                throw new \RuntimeException('Manifest size mismatch: ' . $path);
            }
            $archiveEntry = $archiveEntries[$path] ?? null;
            if (!is_array($archiveEntry) || sprintf('%04o', $archiveEntry['mode']) !== $entry['mode']) {
                throw new \RuntimeException('Manifest mode mismatch: ' . $path);
            }
        }
        $artifactFiles = [];
        foreach ($archiveEntries as $path => $entry) {
            if ($entry['type'] === 'file' && $path !== 'release-manifest.json') {
                $artifactFiles[] = $path;
            }
        }
        sort($artifactFiles, SORT_STRING);
        $manifestFiles = array_keys($seen);
        sort($manifestFiles, SORT_STRING);
        if ($artifactFiles !== $manifestFiles) {
            throw new \RuntimeException('Release manifest does not exactly cover artifact files.');
        }
    }

    private function verifyPhpSyntax(string $root): int
    {
        $count = 0;
        foreach (FileSystem::files($root) as $file) {
            if (!str_ends_with(strtolower($file), '.php')) {
                continue;
            }
            $count++;
            $contents = file_get_contents($file);
            if (!is_string($contents)) {
                throw new \RuntimeException('Artifact PHP file is unreadable: ' . FileSystem::relative($root, $file));
            }
            try {
                $tokens = token_get_all($contents, TOKEN_PARSE);
                if ($tokens === []) {
                    throw new \ParseError('tokenization returned no tokens');
                }
            } catch (\ParseError $error) {
                throw new \RuntimeException(
                    'Artifact PHP syntax failed: ' . FileSystem::relative($root, $file),
                    0,
                    $error,
                );
            }
        }
        return $count;
    }

    /** @param array<string, string> $environment
     * @return array<string, string>
     */
    private function verifyCli(string $root, array $environment): array
    {
        $commands = [
            'about' => ['app:about'],
            'help' => ['help'],
            'schedule' => ['schedule:list'],
            'worker' => ['worker:run', '--once'],
        ];
        if ($this->hasMySqlEnvironment()) {
            $commands['migration_status'] = ['db:migrate:status'];
        }
        $results = [];
        foreach ($commands as $name => $arguments) {
            $result = $this->runner->run(array_merge([PHP_BINARY, 'bin/console'], $arguments), $root, $environment);
            if ($result->exitCode !== 0) {
                throw new \RuntimeException('Artifact CLI smoke failed for ' . $name . ': ' . $result->output());
            }
            if (
                str_contains($result->output(), $root)
                || preg_match('/-----BEGIN .*PRIVATE KEY-----/', $result->output()) === 1
            ) {
                throw new \RuntimeException('Artifact CLI smoke disclosed unsafe data.');
            }
            $results[$name] = 'pass';
        }
        return $results;
    }

    /** @param array<string, string> $environment
     * @return array<string, mixed>
     */
    private function verifyHttp(string $root, array $environment): array
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        if (!is_resource($socket)) {
            throw new \RuntimeException('Unable to allocate HTTP smoke port: ' . $errorMessage);
        }
        $name = stream_socket_get_name($socket, false);
        fclose($socket);
        if (!is_string($name) || !str_contains($name, ':')) {
            throw new \RuntimeException('Unable to determine HTTP smoke port.');
        }
        $separator = strrchr($name, ':');
        if (!is_string($separator)) {
            throw new \RuntimeException('Unable to parse HTTP smoke port.');
        }
        $port = (int) substr($separator, 1);
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', 'public', 'public/index.php'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $root,
            $environment,
            ['bypass_shell' => true],
        );
        if (!is_resource($process)) {
            throw new \RuntimeException('Unable to start artifact HTTP server.');
        }
        fclose($pipes[0]);
        try {
            $base = 'http://127.0.0.1:' . $port;
            $live = null;
            for ($attempt = 0; $attempt < 40; $attempt++) {
                $live = $this->request($base . '/health/live');
                if ($live['status'] !== 0) {
                    break;
                }
                usleep(100_000);
            }
            if ($live['status'] !== 200) {
                throw new \RuntimeException('Artifact HTTP server did not become healthy.');
            }
            $english = $this->request($base . '/?lang=en');
            $arabic = $this->request($base . '/?lang=ar');
            $fragment = $this->request($base . '/system/about', 'GET', ['Accept: text/vnd.qmdb.fragment+html']);
            $about = $this->request($base . '/system/about');
            $status = $this->request($base . '/system/status');
            $api = $this->request($base . '/api/v1/system/about');
            $ready = $this->request($base . '/health/ready');
            $head = $this->request($base . '/', 'HEAD');
            $options = $this->request($base . '/', 'OPTIONS');
            $missing = $this->request($base . '/not-found');
            $method = $this->request($base . '/', 'POST');
            $successfulResponses = [
                'english' => $english,
                'arabic' => $arabic,
                'about' => $about,
                'status' => $status,
                'api' => $api,
            ];
            foreach ($successfulResponses as $label => $response) {
                if ($response['status'] !== 200) {
                    throw new \RuntimeException('Artifact HTTP ' . $label . ' smoke failed.');
                }
            }
            if (
                !str_contains($arabic['body'], 'dir="rtl"')
                || !str_contains($fragment['content_type'], 'text/vnd.qmdb.fragment+html')
            ) {
                throw new \RuntimeException('Artifact Arabic RTL or fragment response validation failed.');
            }
            if (
                !str_contains($english['headers'], 'content-security-policy:')
                || !str_contains($english['headers'], "'nonce-")
                || !str_contains($english['headers'], 'x-request-id:')
            ) {
                throw new \RuntimeException('Artifact HTTP security headers are incomplete.');
            }
            if (
                $head['status'] !== 200
                || $options['status'] !== 204
                || $missing['status'] !== 404
                || $method['status'] !== 405
            ) {
                throw new \RuntimeException('Artifact HTTP method/error matrix failed.');
            }
            $expectedReady = $this->hasMySqlEnvironment() ? 200 : 503;
            $leaksDatabaseDetails = $expectedReady === 503
                && preg_match('/(?:password|dsn|sqlstate|mysql:host)/i', $ready['body']) === 1;
            if ($ready['status'] !== $expectedReady || $leaksDatabaseDetails) {
                throw new \RuntimeException('Artifact readiness behavior is invalid.');
            }
            foreach (['public/assets/css/base.css', 'public/assets/js/app.js'] as $asset) {
                if (!is_file($root . '/' . $asset)) {
                    throw new \RuntimeException('Artifact frontend asset is missing: ' . $asset);
                }
            }
            return [
                'english' => 'pass', 'arabic_rtl' => 'pass', 'fragment' => 'pass',
                'security_headers' => 'pass', 'request_id' => 'pass',
                'method_matrix' => 'pass', 'readiness_status' => $ready['status'],
            ];
        } finally {
            proc_terminate($process);
            foreach ([1, 2] as $index) {
                stream_get_contents($pipes[$index]);
                fclose($pipes[$index]);
            }
            proc_close($process);
        }
    }

    /** @param list<string> $headers
     * @return array{status: int, headers: string, content_type: string, body: string}
     */
    private function request(string $url, string $method = 'GET', array $headers = []): array
    {
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'ignore_errors' => true,
            'timeout' => 10,
        ]]);
        $body = @file_get_contents($url, false, $context);
        $responseHeaders = http_get_last_response_headers() ?? [];
        $status = 0;
        if (isset($responseHeaders[0]) && preg_match('#\s(\d{3})\s#', $responseHeaders[0], $match) === 1) {
            $status = (int) $match[1];
        }
        $joined = strtolower(implode("\n", $responseHeaders));
        $contentType = '';
        foreach ($responseHeaders as $header) {
            if (str_starts_with(strtolower($header), 'content-type:')) {
                $contentType = trim(substr($header, strlen('content-type:')));
            }
        }
        return [
            'status' => $status,
            'headers' => $joined,
            'content_type' => $contentType,
            'body' => is_string($body) ? $body : '',
        ];
    }

    /** @return array<string, string> */
    private function testEnvironment(): array
    {
        $current = getenv();
        $environment = [];
        foreach ($current as $name => $value) {
            $environment[$name] = $value;
        }
        $environment = array_replace($environment, [
            'APP_ENV' => 'test', 'APP_DEBUG' => 'false', 'APP_TIMEZONE' => 'UTC', 'APP_LOG_LEVEL' => 'emergency',
            'WORKER_MAX_JOBS' => '1', 'WORKER_MAX_RUNTIME_SECONDS' => '5', 'WORKER_IDLE_SLEEP_MS' => '1',
            'WORKER_MAX_MEMORY_MB' => '128', 'WORKER_REQUIRE_PCNTL_IN_PRODUCTION' => 'false',
            'DB_HOST' => '127.0.0.1', 'DB_PORT' => '1', 'DB_NAME' => 'qmdb_test', 'DB_USERNAME' => 'qmdb_test',
            'DB_PASSWORD' => 'qmdb-test-only-unavailable', 'DB_TLS_MODE' => 'disabled', 'DB_TLS_CA_FILE' => '',
            'DB_CONNECT_TIMEOUT_SECONDS' => '1', 'DB_DEADLOCK_MAX_ATTEMPTS' => '1',
            'DB_DEADLOCK_BASE_DELAY_MS' => '0', 'DB_DEADLOCK_MAX_DELAY_MS' => '0',
            'DB_SCHEMA_USERNAME' => 'qmdb_schema_test', 'DB_SCHEMA_PASSWORD' => 'qmdb-test-only-unavailable',
            'DB_SCHEMA_LOCK_TIMEOUT_SECONDS' => '1',
        ]);
        if ($this->hasMySqlEnvironment()) {
            $map = [
                'QMDB_TEST_DB_HOST' => 'DB_HOST', 'QMDB_TEST_DB_PORT' => 'DB_PORT', 'QMDB_TEST_DB_NAME' => 'DB_NAME',
                'QMDB_TEST_DB_USERNAME' => 'DB_USERNAME', 'QMDB_TEST_DB_PASSWORD' => 'DB_PASSWORD',
                'QMDB_TEST_DB_TLS_MODE' => 'DB_TLS_MODE', 'QMDB_TEST_DB_TLS_CA_FILE' => 'DB_TLS_CA_FILE',
                'QMDB_TEST_DB_SCHEMA_USERNAME' => 'DB_SCHEMA_USERNAME',
                'QMDB_TEST_DB_SCHEMA_PASSWORD' => 'DB_SCHEMA_PASSWORD',
            ];
            foreach ($map as $source => $target) {
                $value = getenv($source);
                if (is_string($value)) {
                    $environment[$target] = $value;
                }
            }
        }
        return $environment;
    }

    private function hasMySqlEnvironment(): bool
    {
        $names = [
            'QMDB_TEST_DB_HOST',
            'QMDB_TEST_DB_PORT',
            'QMDB_TEST_DB_NAME',
            'QMDB_TEST_DB_USERNAME',
            'QMDB_TEST_DB_PASSWORD',
        ];
        foreach ($names as $name) {
            $value = getenv($name);
            if (!is_string($value) || trim($value) === '') {
                return false;
            }
        }
        return true;
    }

    /** @return array{gitleaks: string, trivy: string} */
    private function verifyExternalSecurityTools(string $sourceRoot, string $extracted): array
    {
        $binarySuffix = PHP_OS_FAMILY === 'Windows' ? '.exe' : '';
        $gitleaks = $sourceRoot . '/.build/security-tools/bin/gitleaks' . $binarySuffix;
        $trivy = $sourceRoot . '/.build/security-tools/bin/trivy' . $binarySuffix;
        $required = getenv('CI') === 'true';
        $statuses = ['gitleaks' => 'unavailable', 'trivy' => 'unavailable'];
        if (is_file($gitleaks)) {
            $result = $this->runner->run([
                $gitleaks,
                'dir',
                '--no-banner',
                '--redact',
                '--config',
                $sourceRoot . '/tools/security/gitleaks.toml',
                '--report-format',
                'json',
                '--report-path',
                $sourceRoot . '/build/reports/gitleaks-artifact.json',
                $extracted,
            ], $sourceRoot);
            if ($result->exitCode !== 0) {
                throw new \RuntimeException('Gitleaks artifact scan failed.');
            }
            $statuses['gitleaks'] = 'pass';
        } elseif ($required) {
            throw new \RuntimeException('Pinned Gitleaks is mandatory in CI artifact verification.');
        }
        if (is_file($trivy)) {
            $result = $this->runner->run([
                $trivy,
                'filesystem',
                '--scanners',
                'vuln,secret,misconfig',
                '--severity',
                'HIGH,CRITICAL',
                '--exit-code',
                '1',
                '--format',
                'json',
                '--output',
                $sourceRoot . '/build/reports/trivy-artifact.json',
                $extracted,
            ], $sourceRoot);
            if ($result->exitCode !== 0) {
                throw new \RuntimeException('Trivy artifact scan failed.');
            }
            $statuses['trivy'] = 'pass';
        } elseif ($required) {
            throw new \RuntimeException('Pinned Trivy is mandatory in CI artifact verification.');
        }
        return $statuses;
    }

    private function checksumFor(string $path, string $filename): string
    {
        $contents = file_get_contents($path);
        if (!is_string($contents)) {
            throw new \RuntimeException('SHA256SUMS is missing.');
        }
        foreach (preg_split('/\R/', trim($contents)) ?: [] as $line) {
            if (preg_match('/^([a-f0-9]{64})  (.+)$/', $line, $match) === 1 && $match[2] === $filename) {
                return $match[1];
            }
        }
        throw new \RuntimeException('Archive checksum is absent from SHA256SUMS.');
    }

    private function hash(string $path): string
    {
        $hash = hash_file('sha256', $path);
        if (!is_string($hash)) {
            throw new \RuntimeException('Unable to hash file: ' . $path);
        }
        return $hash;
    }

    private function removeTemporary(string $path): void
    {
        $normalizedPath = str_replace('\\', '/', $path);
        $temporaryPrefix = str_replace('\\', '/', rtrim(sys_get_temp_dir(), '\\/')) . '/qmdb-release-verify-';
        if (!str_starts_with($normalizedPath, $temporaryPrefix)) {
            throw new \RuntimeException('Refusing to remove an unexpected temporary path.');
        }
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
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($path);
    }
}
