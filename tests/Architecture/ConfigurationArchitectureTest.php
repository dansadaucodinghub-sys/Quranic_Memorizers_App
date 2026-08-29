<?php

declare(strict_types=1);

namespace Qmdb\Tests\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use RuntimeException;
use SplFileInfo;

final class ConfigurationArchitectureTest extends TestCase
{
    private const APPROVED_NATIVE_ENVIRONMENT_READER =
        'src/Shared/Configuration/Infrastructure/DotenvEnvironmentLoader.php';
    private const APPROVED_SAPI_REQUEST_READER =
        'src/Shared/Http/Request/NativeServerRequestFactory.php';

    public function testNativeEnvironmentAccessIsConfinedToOneApprovedAdapter(): void
    {
        $getenvReaders = [];

        foreach ($this->phpFilesUnder($this->projectRoot() . '/src') as $path) {
            $source = $this->readFile($path);
            $relativePath = $this->relativePath($path);

            if (preg_match('/\bgetenv\s*\(/', $source) === 1) {
                $getenvReaders[] = $relativePath;
            }

            if ($relativePath !== self::APPROVED_SAPI_REQUEST_READER) {
                self::assertDoesNotMatchRegularExpression(
                    '/\$_(?:ENV|SERVER)\b/',
                    $source,
                    sprintf('%s reads native environment state outside the approved adapter.', $relativePath),
                );
            }
        }

        self::assertSame([self::APPROVED_NATIVE_ENVIRONMENT_READER], $getenvReaders);
    }

    public function testSourceNeverMutatesTheProcessEnvironment(): void
    {
        foreach ($this->phpFilesUnder($this->projectRoot() . '/src') as $path) {
            self::assertDoesNotMatchRegularExpression(
                '/\bputenv\s*\(/',
                $this->readFile($path),
                sprintf('%s must not mutate the process environment.', $this->relativePath($path)),
            );
        }
    }

    public function testDotenvIgnorePolicyAndSafeExampleExist(): void
    {
        $gitignore = $this->readFile($this->projectRoot() . '/.gitignore');

        self::assertMatchesRegularExpression('/^\.env$/m', $gitignore);
        self::assertMatchesRegularExpression('/^\.env\.\*$/m', $gitignore);
        self::assertMatchesRegularExpression('/^!\.env\.example$/m', $gitignore);
        self::assertFileExists($this->projectRoot() . '/.env.example');
    }

    public function testOnlyTheSafeDotenvExampleIsTrackedWhenGitMetadataIsAvailable(): void
    {
        if (!is_dir($this->projectRoot() . '/.git')) {
            self::markTestSkipped('Git metadata is unavailable in this workspace snapshot.');
        }

        $trackedFiles = $this->trackedDotenvFiles();
        sort($trackedFiles);

        self::assertSame(['.env.example'], $trackedFiles);
    }

    public function testDotenvExampleContainsOnlyApprovedNonSecretAssignments(): void
    {
        $assignments = [];

        foreach (file($this->projectRoot() . '/.env.example', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            self::assertMatchesRegularExpression('/\A[A-Z][A-Z0-9_]*=/', $trimmed);
            [$name] = explode('=', $trimmed, 2);
            $assignments[] = $name;
            if (
                in_array($name, [
                'AUTH_CSRF_SIGNING_KEY',
                'AUTH_IDENTITY_HMAC_KEY',
                'AUTH_CONTACT_ENCRYPTION_KEY',
                'AUTH_MFA_ENCRYPTION_KEY',
                'DB_PASSWORD',
                'DB_SCHEMA_PASSWORD',
                ], true)
            ) {
                self::assertSame($name . '=', $trimmed);
            } elseif (!str_starts_with($name, 'AUTH_')) {
                self::assertDoesNotMatchRegularExpression(
                    '/(?:SECRET|PASSWORD|TOKEN|PRIVATE|CREDENTIAL|CERTIFICATE|KEY)/',
                    $name,
                );
            }
        }

        self::assertSame([
            'APP_ENV',
            'APP_DEBUG',
            'APP_TIMEZONE',
            'APP_LOG_LEVEL',
            'APP_PUBLIC_BASE_URL',
            'AUTH_CSRF_SIGNING_KEY',
            'AUTH_IDENTITY_HMAC_KEY',
            'AUTH_CONTACT_ENCRYPTION_KEY',
            'AUTH_CONTACT_ENCRYPTION_KEY_ID',
            'AUTH_CSRF_TTL_SECONDS',
            'AUTH_FORM_MAX_BYTES',
            'AUTH_PASSWORD_MIN_LENGTH',
            'AUTH_PASSWORD_MAX_BYTES',
            'AUTH_EMAIL_VERIFICATION_TTL_SECONDS',
            'AUTH_EMAIL_VERIFICATION_MAX_ATTEMPTS',
            'AUTH_REGISTRATION_WINDOW_SECONDS',
            'AUTH_REGISTRATION_MAX_ATTEMPTS',
            'AUTH_VERIFICATION_RESEND_WINDOW_SECONDS',
            'AUTH_VERIFICATION_RESEND_MAX_ATTEMPTS',
            'AUTH_PASSWORD_WINDOW_SECONDS',
            'AUTH_PASSWORD_MAX_ATTEMPTS',
            'AUTH_RATE_LIMIT_BLOCK_SECONDS',
            'AUTH_SESSION_IDLE_TTL_SECONDS',
            'AUTH_SESSION_ABSOLUTE_TTL_SECONDS',
            'AUTH_SESSION_ROTATION_INTERVAL_SECONDS',
            'AUTH_SESSION_PREVIOUS_TOKEN_GRACE_SECONDS',
            'AUTH_SESSION_TOUCH_INTERVAL_SECONDS',
            'AUTH_SESSION_MAX_ACTIVE_PER_ACCOUNT',
            'AUTH_DEVICE_COOKIE_TTL_SECONDS',
            'AUTH_PASSWORD_RECOVERY_TTL_SECONDS',
            'AUTH_PASSWORD_RECOVERY_MAX_ATTEMPTS',
            'AUTH_PASSWORD_RECOVERY_REQUEST_WINDOW_SECONDS',
            'AUTH_PASSWORD_RECOVERY_REQUEST_MAX_ATTEMPTS',
            'AUTH_PASSWORD_RECOVERY_CONFIRM_WINDOW_SECONDS',
            'AUTH_PASSWORD_RECOVERY_CONFIRM_MAX_ATTEMPTS',
            'AUTH_SECURITY_NOTIFICATION_BATCH_SIZE',
            'AUTH_SECURITY_NOTIFICATION_MAX_ATTEMPTS',
            'AUTH_SECURITY_NOTIFICATION_LEASE_SECONDS',
            'AUTH_SECURITY_NOTIFICATION_RETRY_BASE_SECONDS',
            'AUTH_SECURITY_NOTIFICATION_RETRY_MAX_SECONDS',
            'AUTH_TRANSACTION_TTL_SECONDS',
            'AUTH_TRANSACTION_MAX_ATTEMPTS',
            'AUTH_STEP_UP_GRANT_TTL_SECONDS',
            'AUTH_STEP_UP_MAX_ATTEMPTS',
            'AUTH_MFA_ENCRYPTION_KEY',
            'AUTH_MFA_ENCRYPTION_KEY_VERSION',
            'AUTH_TOTP_ISSUER',
            'AUTH_TOTP_PERIOD_SECONDS',
            'AUTH_TOTP_DIGITS',
            'AUTH_TOTP_ALLOWED_DRIFT_STEPS',
            'AUTH_TOTP_ENROLLMENT_TTL_SECONDS',
            'AUTH_RECOVERY_CODE_COUNT',
            'AUTH_RECOVERY_CODE_BYTES',
            'AUTH_WEBAUTHN_RP_ID',
            'AUTH_WEBAUTHN_RP_NAME',
            'AUTH_WEBAUTHN_ALLOWED_ORIGINS',
            'AUTH_WEBAUTHN_CHALLENGE_TTL_SECONDS',
            'AUTH_WEBAUTHN_MAX_RESPONSE_BYTES',
            'AUTH_WEBAUTHN_USER_VERIFICATION',
            'AUTH_WEBAUTHN_ATTESTATION',
            'AUTH_WEBAUTHN_PASSWORDLESS_ENABLED',
            'AUTH_MFA_WINDOW_SECONDS',
            'AUTH_MFA_MAX_ATTEMPTS',
            'AUTH_PASSKEY_WINDOW_SECONDS',
            'AUTH_PASSKEY_MAX_ATTEMPTS',
            'AUTH_PRIVILEGED_ACCESS_REQUEST_TTL_SECONDS',
            'AUTH_TEMPORARY_PRIVILEGE_MAX_TTL_SECONDS',
            'AUTH_SUPPORT_ACCESS_MAX_TTL_SECONDS',
            'AUTH_BREAK_GLASS_MAX_TTL_SECONDS',
            'AUTH_PRIVILEGED_ACCESS_MAX_PERMISSIONS',
            'AUTH_PRIVILEGED_ACCESS_REVIEW_TTL_SECONDS',
            'AUTH_PRIVILEGED_ACCESS_JUSTIFICATION_MAX_BYTES',
            'AUTH_PRIVILEGED_ACCESS_REFERENCE_MAX_BYTES',
            'AUTH_PRIVILEGED_ACCESS_MAINTENANCE_BATCH_SIZE',
            'AUTH_PRIVILEGED_ACCESS_REQUEST_WINDOW_SECONDS',
            'AUTH_PRIVILEGED_ACCESS_REQUEST_MAX_ATTEMPTS',
            'AUTH_BREAK_GLASS_WINDOW_SECONDS',
            'AUTH_BREAK_GLASS_MAX_ATTEMPTS',
            'MAILER_DSN',
            'MAIL_FROM_ADDRESS',
            'MAIL_FROM_NAME',
            'WORKER_MAX_JOBS',
            'WORKER_MAX_RUNTIME_SECONDS',
            'WORKER_IDLE_SLEEP_MS',
            'WORKER_MAX_MEMORY_MB',
            'WORKER_REQUIRE_PCNTL_IN_PRODUCTION',
            'SCHEDULER_RUN_LEASE_SECONDS',
            'SCHEDULER_LOCK_TIMEOUT_SECONDS',
            'DB_HOST',
            'DB_PORT',
            'DB_NAME',
            'DB_USERNAME',
            'DB_PASSWORD',
            'DB_TLS_MODE',
            'DB_TLS_CA_FILE',
            'DB_CONNECT_TIMEOUT_SECONDS',
            'DB_DEADLOCK_MAX_ATTEMPTS',
            'DB_DEADLOCK_BASE_DELAY_MS',
            'DB_DEADLOCK_MAX_DELAY_MS',
            'DB_SCHEMA_USERNAME',
            'DB_SCHEMA_PASSWORD',
            'DB_SCHEMA_LOCK_TIMEOUT_SECONDS',
        ], $assignments);
    }

    public function testSecretValueCannotBeImplicitlyRenderedOrSerializedInPlaintext(): void
    {
        $reflection = new ReflectionClass(\Qmdb\Shared\Security\Secrets\SecretValue::class);

        self::assertFalse($reflection->hasMethod('__toString'));
        self::assertTrue($reflection->hasMethod('__debugInfo'));
        self::assertTrue($reflection->hasMethod('__serialize'));
        self::assertTrue($reflection->hasMethod('jsonSerialize'));
    }

    public function testConfigurationObjectsExposeNoUnrestrictedRawArrayMethod(): void
    {
        $forbiddenMethodNames = ['all', 'dump', 'raw', 'toArray', 'values'];

        foreach (
            [
                \Qmdb\Shared\Configuration\ApplicationConfiguration::class,
                \Qmdb\Shared\Configuration\EnvironmentVariables::class,
            ] as $className
        ) {
            $reflection = new ReflectionClass($className);
            $publicMethodNames = array_map(
                static fn (\ReflectionMethod $method): string => $method->getName(),
                $reflection->getMethods(\ReflectionMethod::IS_PUBLIC),
            );

            self::assertSame([], array_values(array_intersect($forbiddenMethodNames, $publicMethodNames)));
        }
    }

    public function testPublicHttpPayloadSourceContainsNoConfigurationDetails(): void
    {
        $source = $this->readFile(
            $this->projectRoot() . '/src/Shared/Http/Controller/SystemAboutController.php',
        );

        foreach (['environment', 'debug', 'timezone', 'source', 'secret', 'php_version'] as $field) {
            self::assertStringNotContainsString(sprintf("'%s' =>", $field), strtolower($source));
        }
    }

    public function testDatabaseConfigurationIsConfinedToApprovedNamespaces(): void
    {
        self::assertDirectoryDoesNotExist($this->projectRoot() . '/src/Database');
        self::assertFileExists($this->projectRoot() . '/database/migrations.php');
        self::assertFileExists($this->projectRoot() . '/database/seeds.php');

        foreach ($this->phpFilesUnder($this->projectRoot() . '/src/Shared/Configuration') as $path) {
            $source = $this->readFile($path);
            if (preg_match('/\b(?:database|dsn|mysql|password|username|host|port)\b/i', $source) !== 1) {
                continue;
            }
            self::assertStringContainsString('/Shared/Configuration/Database/', $path);
        }
    }

    public function testNoCloudSpecificSecretsProviderWasIntroduced(): void
    {
        foreach ($this->phpFilesUnder($this->projectRoot() . '/src/Shared/Security/Secrets') as $path) {
            self::assertDoesNotMatchRegularExpression(
                '/\b(?:AWS|Azure|GoogleCloud|Vault|KMS|KeyVault|SecretsManager)\b/i',
                $this->readFile($path),
                sprintf('%s selects a cloud-specific secret provider.', $this->relativePath($path)),
            );
        }
    }

    public function testNoGenericServiceLocatorWasIntroduced(): void
    {
        foreach ($this->phpFilesUnder($this->projectRoot() . '/src') as $path) {
            if (str_ends_with($path, '/Shared/DependencyInjection/CompiledContainer.php')) {
                continue;
            }

            self::assertDoesNotMatchRegularExpression(
                '/function\s+get\s*\(\s*string\s+\$[a-zA-Z_][a-zA-Z0-9_]*\s*\)\s*:\s*mixed/',
                $this->readFile($path),
                sprintf('%s contains a generic service-locator method.', $this->relativePath($path)),
            );
        }
    }

    public function testComposerContainsNoFrameworkPackage(): void
    {
        $composer = json_decode(
            $this->readFile($this->projectRoot() . '/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($composer);
        $packages = array_merge(
            array_keys(is_array($composer['require'] ?? null) ? $composer['require'] : []),
            array_keys(is_array($composer['require-dev'] ?? null) ? $composer['require-dev'] : []),
        );

        self::assertSame([], array_values(array_intersect([
            'laravel/framework',
            'symfony/framework-bundle',
            'slim/slim',
            'laminas/laminas-mvc',
            'cakephp/cakephp',
            'codeigniter4/framework',
        ], $packages)));
    }

    public function testRuntimeIdentifiersUseOnlyApprovedSecureRandomness(): void
    {
        $source = $this->readFile(
            $this->projectRoot() . '/src/Shared/Identifier/SecureRandomRuntimeIdentifierGenerator.php',
        );

        self::assertStringContainsString('random_bytes(', $source);
        self::assertStringContainsString('ENTROPY_BYTES = 16', $source);
        self::assertDoesNotMatchRegularExpression('/\b(?:rand|mt_rand|uniqid)\s*\(/', $source);
    }

    public function testSystemClockUsesImmutableUtcTime(): void
    {
        $source = $this->readFile($this->projectRoot() . '/src/Shared/Time/SystemClock.php');

        self::assertStringContainsString('DateTimeImmutable', $source);
        self::assertStringContainsString("new DateTimeZone('UTC')", $source);
        self::assertStringNotContainsString('DateTime(', $source);
    }

    public function testApplicationIsNotAnArrayBackedServiceRegistry(): void
    {
        $source = $this->readFile($this->projectRoot() . '/src/Bootstrap/Application.php');

        self::assertDoesNotMatchRegularExpression('/array\s+\$(?:services|container|bindings)/i', $source);
        self::assertDoesNotMatchRegularExpression('/function\s+get\s*\(\s*string/', $source);
    }

    public function testImplementationReportContainsNoTestSecretOrEnvironmentDump(): void
    {
        $path = $this->projectRoot()
            . '/docs/implementation/reports/QMDB-P1-B02-implementation-report.md';
        self::assertFileExists($path);
        $report = $this->readFile($path);

        self::assertDoesNotMatchRegularExpression('/QMDB_(?:TEST|DOTENV)_SECRET_[A-Za-z0-9_]+/', $report);
        self::assertStringNotContainsString('QMDB_TEST_SECRET_8f30f8e1_DO_NOT_EXPOSE', $report);
        self::assertStringNotContainsString('process environment dump', strtolower($report));
    }

    /** @return list<string> */
    private function trackedDotenvFiles(): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open(
            ['git', 'ls-files', '.env', '.env.*'],
            $descriptors,
            $pipes,
            $this->projectRoot(),
        );

        if (!is_resource($process)) {
            throw new RuntimeException('Unable to execute the Git secret-tracking check.');
        }

        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0 || $output === false || $error === false) {
            throw new RuntimeException('The Git secret-tracking check failed.');
        }

        $lines = preg_split('/\R/', trim($output));

        return $lines === false || $lines === [''] ? [] : $lines;
    }

    /** @return list<string> */
    private function phpFilesUnder(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = str_replace('\\', '/', $file->getPathname());
            }
        }

        sort($files);

        return $files;
    }

    private function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    private function relativePath(string $path): string
    {
        return ltrim(str_replace('\\', '/', substr($path, strlen($this->projectRoot()))), '/');
    }

    private function readFile(string $path): string
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Unable to read project file: %s', $path));
        }

        return $contents;
    }
}
