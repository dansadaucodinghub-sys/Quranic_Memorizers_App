<?php

declare(strict_types=1);

namespace Qmdb\Tests\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Kernel\HttpKernel;
use Qmdb\Shared\Http\Middleware\ExceptionHandlingMiddleware;
use Qmdb\Shared\Http\Request\NativeServerRequestFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use RuntimeException;
use SplFileInfo;

final class HttpArchitectureTest extends TestCase
{
    public function testPsr7AndPsr15ContractsDefineTheHttpBoundaries(): void
    {
        $controllerMethod = (new ReflectionClass(Controller::class))->getMethod('handle');
        $kernel = new ReflectionClass(HttpKernel::class);
        $middleware = new ReflectionClass(ExceptionHandlingMiddleware::class);

        self::assertSame(
            ServerRequestInterface::class,
            (string) $controllerMethod->getParameters()[0]->getType(),
        );
        self::assertSame(ResponseInterface::class, (string) $controllerMethod->getReturnType());
        self::assertTrue($kernel->implementsInterface(RequestHandlerInterface::class));
        self::assertTrue($middleware->implementsInterface(MiddlewareInterface::class));
    }

    public function testNoDuplicateProjectRequestOrResponseContractExists(): void
    {
        foreach ($this->phpFilesUnder($this->root() . '/src') as $path) {
            $source = $this->read($path);
            self::assertDoesNotMatchRegularExpression('/interface\s+(?:Request|Response)\b/', $source);
        }
    }

    public function testOnlyNativeFactoryReadsSapiRequestState(): void
    {
        $nativePath = $this->root() . '/src/Shared/Http/Request/NativeServerRequestFactory.php';
        foreach ($this->phpFilesUnder($this->root() . '/src') as $path) {
            $hasSuperglobal = preg_match('/\$_(?:GET|POST|SERVER|COOKIE|FILES|REQUEST)\b/', $this->read($path)) === 1;
            self::assertSame($path === $nativePath, $hasSuperglobal, $path);
        }
    }

    public function testOnlySapiEmitterCanEmitUnderSourceTree(): void
    {
        $emitterPath = $this->root() . '/src/Shared/Http/Response/SapiResponseEmitter.php';
        foreach ($this->phpFilesUnder($this->root() . '/src') as $path) {
            $source = $this->read($path);
            $emits = preg_match('/\b(?:header|http_response_code)\s*\(|\becho\b/', $source) === 1;
            self::assertSame($path === $emitterPath, $emits, $path);
        }
    }

    public function testPhpVersionDisclosureHeaderIsRemovedAtBothSapiBoundaries(): void
    {
        $emitter = $this->read($this->root() . '/src/Shared/Http/Response/SapiResponseEmitter.php');
        $fallback = $this->read($this->root() . '/public/index.php');

        self::assertStringContainsString("header_remove('X-Powered-By')", $emitter);
        self::assertStringContainsString("header_remove('X-Powered-By')", $fallback);
    }

    public function testControllersHaveNoSapiDatabaseOrDynamicInvocationBehavior(): void
    {
        foreach ($this->phpFilesUnder($this->root() . '/src/Shared/Http/Controller') as $path) {
            $source = $this->read($path);
            self::assertDoesNotMatchRegularExpression(
                '/\$_|\b(?:header|http_response_code|call_user_func|eval|exit)\s*\(|\becho\b/i',
                $source,
                $path,
            );
            self::assertDoesNotMatchRegularExpression(
                '/\b(?:PDO|mysqli|SELECT\s+|INSERT\s+|UPDATE\s+|DELETE\s+FROM)\b/i',
                $source,
                $path,
            );
        }
    }

    public function testKernelHasNoSapiEnvironmentOrInfrastructureBehavior(): void
    {
        $source = $this->read($this->root() . '/src/Shared/Http/Kernel/HttpKernel.php');

        self::assertDoesNotMatchRegularExpression(
            '/\$_|\b(?:getenv|header|http_response_code|echo|exit|PDO|mysqli|Redis|Dotenv)\b/i',
            $source,
        );
    }

    public function testNoForwardedTrustOrMethodOverrideLogicExists(): void
    {
        foreach ($this->phpFilesUnder($this->root() . '/src') as $path) {
            $source = $this->read($path);
            self::assertStringNotContainsString('HTTP_METHOD_OVERRIDE', strtoupper($source), $path);
            self::assertStringNotContainsString('X-HTTP-METHOD-OVERRIDE', strtoupper($source), $path);

            if (str_contains(strtoupper($source), 'X_FORWARDED_')) {
                self::assertStringEndsWith('/Shared/Http/Request/NativeServerRequestFactory.php', $path);
                self::assertStringContainsString('unset(', $source);
            }
        }
    }

    public function testNoSharedMiddlewareCursorExists(): void
    {
        $pipeline = $this->read($this->root() . '/src/Shared/Http/Middleware/MiddlewarePipeline.php');
        $requestLocal = new ReflectionClass(\Qmdb\Shared\Http\Middleware\MiddlewareRequestHandler::class);

        self::assertStringNotContainsString('$index', $pipeline);
        self::assertTrue($requestLocal->isReadOnly());
        self::assertTrue($requestLocal->getProperty('index')->isReadOnly());
    }

    public function testRouteDefinitionsRemainStaticAndFreeOfBusinessLogic(): void
    {
        $source = $this->read($this->root() . '/routes/web.php');

        self::assertDoesNotMatchRegularExpression(
            '/\$_|\b(?:getenv|PDO|mysqli|authorize|call_user_func|eval)\b|new\s+class\b/i',
            $source,
        );
        $routeNames = [
            'system.home', 'system.about.page', 'system.status.page',
            'system.health.live', 'system.health.ready', 'api.v1.system.about',
            'account.registration.form', 'account.registration.submit', 'account.registration.accepted',
            'account.email_verification.resend.form', 'account.email_verification.resend.submit',
            'account.email_verification.form', 'account.email_verification.submit',
            'account.email_verification.completed',
            'account.login.form', 'account.login.submit', 'account.logout',
            'account.security.sessions', 'account.security.session_revoke.form',
            'account.security.session_revoke.submit', 'account.security.device_revoke.form',
            'account.security.device_revoke.submit',
        ];
        foreach ($routeNames as $name) {
            self::assertStringContainsString($name, $source);
        }
    }

    public function testDependenciesAreLimitedToApprovedFoundationPackages(): void
    {
        $composer = json_decode($this->read($this->root() . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($composer);
        $runtime = $composer['require'] ?? null;
        self::assertIsArray($runtime);

        self::assertSame([
            'php',
            'ext-json',
            'ext-mbstring',
            'ext-pdo',
            'ext-pdo_mysql',
            'monolog/monolog',
            'nyholm/psr7',
            'nyholm/psr7-server',
            'psr/container',
            'psr/http-factory',
            'psr/http-message',
            'psr/http-server-handler',
            'psr/http-server-middleware',
            'psr/log',
            'symfony/mailer',
            'vlucas/phpdotenv',
        ], array_keys($runtime));

        foreach (['nikic/fast-route', 'symfony/routing', 'league/route', 'php-di/php-di'] as $forbidden) {
            self::assertArrayNotHasKey($forbidden, $runtime);
        }
    }

    public function testNoRedisOrBusinessModuleExistsAndSchemaManifestsAreExplicit(): void
    {
        foreach (['src/Database', 'src/Domain', 'src/Modules/Http'] as $directory) {
            self::assertDirectoryDoesNotExist($this->root() . '/' . $directory);
        }

        self::assertFileExists($this->root() . '/database/migrations.php');
        self::assertFileExists($this->root() . '/database/seeds.php');

        foreach ($this->phpFilesUnder($this->root() . '/src') as $path) {
            self::assertDoesNotMatchRegularExpression('/\b(?:mysqli|Redis|RedisStream)\b/', $this->read($path), $path);
        }
    }

    public function testFrozenP0FileHashesRemainValid(): void
    {
        $manifest = $this->read($this->root() . '/docs/closeout/qmdb-p0-baseline-freeze.yaml');
        $matched = preg_match_all(
            '/- path: "([^"]+)"\R\s+category: [^\r\n]+\R\s+sha256: ([a-f0-9]{64})/',
            $manifest,
            $entries,
            PREG_SET_ORDER,
        );
        self::assertSame(82, $matched);

        foreach ($entries as $entry) {
            $path = $this->root() . '/' . $entry[1];
            self::assertFileExists($path);
            self::assertSame($entry[2], hash_file('sha256', $path), $entry[1]);
        }
    }

    public function testPublicFrontControllerIsThinAndSafe(): void
    {
        $source = $this->read($this->root() . '/public/index.php');

        self::assertStringContainsString('createHttpRuntime()->run()', $source);
        self::assertDoesNotMatchRegularExpression('/\$_|\b(?:PDO|mysqli|SELECT|INSERT|UPDATE|DELETE)\b/i', $source);
        self::assertLessThan(130, substr_count($source, "\n"));
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
