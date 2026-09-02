<?php

declare(strict_types=1);

namespace Qmdb\Tests\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class SourceArchitectureTest extends TestCase
{
    public function testEveryProjectPhpSourceAndTestFileUsesStrictTypes(): void
    {
        foreach ($this->projectPhpFiles() as $path) {
            self::assertMatchesRegularExpression(
                '/\A(?:#![^\n]+\n)?<\?php\s+declare\(strict_types=1\);/',
                $this->readFile($path),
                sprintf('%s must declare strict types at the beginning of the PHP source.', $path),
            );
        }
    }

    public function testEverySourceFileUsesTheApprovedNamespace(): void
    {
        foreach ($this->phpFilesUnder($this->projectRoot() . '/src') as $path) {
            self::assertMatchesRegularExpression(
                '/\bnamespace Qmdb(?:\\\\[A-Za-z][A-Za-z0-9]*)*;/',
                $this->readFile($path),
                sprintf('%s must use the approved Qmdb namespace.', $path),
            );
        }
    }

    public function testNoFullStackFrameworkPackageIsPresent(): void
    {
        $composer = $this->composerConfiguration();
        $runtimePackages = $composer['require'] ?? null;
        $developmentPackages = $composer['require-dev'] ?? null;

        self::assertIsArray($runtimePackages);
        self::assertIsArray($developmentPackages);

        $packages = array_merge(
            array_keys($runtimePackages),
            array_keys($developmentPackages),
        );
        $forbiddenPackages = [
            'laravel/framework',
            'symfony/framework-bundle',
            'slim/slim',
            'laminas/laminas-mvc',
            'cakephp/cakephp',
            'codeigniter4/framework',
            'doctrine/orm',
            'illuminate/database',
        ];

        self::assertSame([], array_values(array_intersect($forbiddenPackages, $packages)));
    }

    public function testOnlyNativeRequestFactoryMayReadSapiSuperglobals(): void
    {
        foreach ($this->phpFilesUnder($this->projectRoot() . '/src') as $path) {
            if (str_ends_with($path, '/Shared/Http/Request/NativeServerRequestFactory.php')) {
                continue;
            }

            self::assertDoesNotMatchRegularExpression(
                '/\$_(?:GET|POST|REQUEST|SERVER|COOKIE|FILES|ENV|SESSION)\b/',
                $this->readFile($path),
                sprintf('%s must not read a superglobal.', $path),
            );
        }
    }

    public function testSourceDoesNotUseUnsafeRuntimeFunctions(): void
    {
        $unsafeCall = '/\b(?:eval|shell_exec|system|passthru|popen|unserialize)\s*\(|(?<!->)\bexec\s*\(/i';

        foreach ($this->phpFilesUnder($this->projectRoot() . '/src') as $path) {
            self::assertDoesNotMatchRegularExpression(
                $unsafeCall,
                $this->readFile($path),
                sprintf('%s must not use prohibited runtime functions.', $path),
            );
        }
    }

    public function testDatabaseImplementationIsConfinedToApprovedBoundaries(): void
    {
        $root = $this->projectRoot();

        self::assertDirectoryDoesNotExist($root . '/src/Database');
        self::assertFileExists($root . '/database/migrations.php');
        self::assertFileExists($root . '/database/seeds.php');

        foreach ($this->phpFilesUnder($root . '/src') as $path) {
            $source = $this->readFile($path);
            $databasePattern = '/(?:\bPDO\b|\bmysqli\b|'
                . '\b(?:SELECT|INSERT|UPDATE|DELETE)\s+(?:FROM|INTO|SET|\*)\b)/i';
            if (preg_match($databasePattern, $source) !== 1) {
                continue;
            }
            self::assertTrue(
                str_contains($path, '/Shared/Infrastructure/Persistence/MySql/')
                || str_contains($path, '/Modules/Identity/Infrastructure/Persistence/')
                || str_contains($path, '/Modules/Geography/Infrastructure/Persistence/')
                || str_contains($path, '/Modules/Geography/Infrastructure/Seed/')
                || str_contains($path, '/Modules/People/Infrastructure/Persistence/')
                || str_contains($path, '/Modules/Organizations/Infrastructure/Persistence/')
                || str_contains($path, '/Modules/Organizations/Infrastructure/Seed/')
                || str_contains($path, '/Modules/OrganizationAffiliations/Infrastructure/Persistence/')
                || str_contains($path, '/Modules/OrganizationAffiliations/Infrastructure/Seed/')
                || str_contains($path, '/Modules/IdentityAccess/Infrastructure/Persistence/')
                || str_contains($path, '/Modules/IdentityAccountState/Infrastructure/Persistence/')
                || str_contains($path, '/Modules/IdentityAccountState/Infrastructure/Seed/')
                || str_contains($path, '/Modules/IdentityMultiFactor/Infrastructure/Persistence/')
                || str_contains($path, '/Modules/IdentityRecovery/Infrastructure/Persistence/')
                || str_contains($path, '/Modules/IdentitySecurityNotifications/Infrastructure/Persistence/')
                || str_contains($path, '/Modules/IdentitySessions/Infrastructure/Persistence/')
                || str_contains($path, '/Modules/SecurityAuthorization/Infrastructure/Persistence/')
                || str_contains($path, '/Modules/SecurityAuthorization/Infrastructure/Seed/')
                || str_contains($path, '/Modules/SecurityAudit/Infrastructure/Persistence/')
                || str_contains($path, '/Modules/SecurityPrivilegedAccess/Infrastructure/Persistence/')
                || str_contains($path, '/Modules/SecurityPrivilegedAccess/Infrastructure/Seed/')
                || str_contains($path, '/Modules/Tenancy/Infrastructure/Persistence/')
                || str_contains($path, '/Modules/TenancyContext/Infrastructure/Persistence/')
                || str_ends_with($path, '/Shared/Database/Connection/DatabaseConnectionProvider.php')
                || str_contains($path, '/Shared/Schema/')
                || str_contains($path, '/Shared/Background/Scheduler/Infrastructure/'),
                sprintf('%s contains database behavior outside the approved boundary.', $path),
            );
        }
    }

    public function testOnlyAuthorizedP2AndP3B04DomainModulesExist(): void
    {
        $modules = glob($this->projectRoot() . '/src/Modules/*', GLOB_ONLYDIR);
        self::assertIsArray($modules);
        self::assertSame(
            [
                'Geography',
                'Identity',
                'IdentityAccess',
                'IdentityAccountState',
                'IdentityMultiFactor',
                'IdentityRecovery',
                'IdentitySecurityNotifications',
                'IdentitySessions',
                'OrganizationAffiliations',
                'Organizations',
                'People',
                'SecurityAudit',
                'SecurityAuthorization',
                'SecurityPrivilegedAccess',
                'SecurityWeb',
                'Tenancy',
                'TenancyContext',
            ],
            array_map('basename', $modules),
        );
        self::assertDirectoryDoesNotExist($this->projectRoot() . '/src/Modules/Authorization');
    }

    public function testPublicIndexIsTheOnlyPhpWebEntryPoint(): void
    {
        $files = $this->phpFilesUnder($this->projectRoot() . '/public');

        self::assertCount(1, $files);
        self::assertSame('index.php', basename($files[0]));
    }

    public function testNoPrivateKeyOrCertificateFileExistsInTheProjectTree(): void
    {
        $forbiddenNames = ['id_rsa', 'id_ed25519'];
        $forbiddenSuffixes = ['.key', '.pem', '.p12', '.pfx'];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $this->projectRoot(),
                FilesystemIterator::SKIP_DOTS,
            ),
        );

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());

            if (
                str_contains($path, '/vendor/')
                || str_contains($path, '/.git/')
                || str_contains($path, '/.runtime/')
            ) {
                continue;
            }

            $name = strtolower($file->getFilename());
            self::assertNotContains($name, $forbiddenNames, sprintf('%s is a private-key file.', $path));

            foreach ($forbiddenSuffixes as $suffix) {
                self::assertFalse(str_ends_with($name, $suffix), sprintf('%s is a private-key file.', $path));
            }
        }
    }

    public function testComposerLockExists(): void
    {
        self::assertFileExists($this->projectRoot() . '/composer.lock');
    }

    public function testAutoloadMappingsMatchApprovedNamespaces(): void
    {
        $composer = $this->composerConfiguration();
        $autoload = $composer['autoload'] ?? null;
        $autoloadDev = $composer['autoload-dev'] ?? null;

        self::assertIsArray($autoload);
        self::assertIsArray($autoloadDev);

        $sourceMappings = $autoload['psr-4'] ?? null;
        $testMappings = $autoloadDev['psr-4'] ?? null;

        self::assertIsArray($sourceMappings);
        self::assertIsArray($testMappings);

        self::assertSame('src/', $sourceMappings['Qmdb\\'] ?? null);
        self::assertSame('tests/', $testMappings['Qmdb\\Tests\\'] ?? null);
    }

    public function testEntryPointsRemainThinAndFreeOfBusinessLogic(): void
    {
        foreach ([$this->projectRoot() . '/public/index.php', $this->projectRoot() . '/bin/console'] as $path) {
            $contents = $this->readFile($path);
            $tokens = token_get_all($contents);
            $significantTokenCount = 0;

            foreach ($tokens as $token) {
                if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }

                $significantTokenCount++;
            }

            self::assertLessThan(300, $significantTokenCount, sprintf('%s is too complex.', $path));
            self::assertDoesNotMatchRegularExpression(
                '/\b(?:class|interface|trait|PDO|mysqli|SELECT|INSERT|UPDATE|DELETE)\b/i',
                $contents,
                sprintf('%s contains implementation outside an entry-point boundary.', $path),
            );
        }
    }

    /** @return list<string> */
    private function projectPhpFiles(): array
    {
        $root = $this->projectRoot();
        $files = array_merge(
            $this->phpFilesUnder($root . '/src'),
            $this->phpFilesUnder($root . '/tests'),
            $this->phpFilesUnder($root . '/public'),
            $this->phpFilesUnder($root . '/routes'),
            [$root . '/bin/console'],
        );
        sort($files);

        return $files;
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
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }

            if (strtolower($file->getExtension()) === 'php') {
                $files[] = str_replace('\\', '/', $file->getPathname());
            }
        }

        sort($files);

        return $files;
    }

    /** @return array<string, mixed> */
    private function composerConfiguration(): array
    {
        $decoded = json_decode(
            $this->readFile($this->projectRoot() . '/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        if (!is_array($decoded)) {
            throw new RuntimeException('Composer configuration must decode to an object.');
        }

        $configuration = [];

        foreach ($decoded as $key => $value) {
            if (!is_string($key)) {
                throw new RuntimeException('Composer configuration keys must be strings.');
            }

            $configuration[$key] = $value;
        }

        return $configuration;
    }

    private function projectRoot(): string
    {
        return dirname(__DIR__, 2);
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
