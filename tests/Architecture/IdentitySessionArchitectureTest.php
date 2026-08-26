<?php

declare(strict_types=1);

namespace Qmdb\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class IdentitySessionArchitectureTest extends TestCase
{
    public function testSessionModuleUsesExplicitBoundedContractsAndNativePersistence(): void
    {
        $root = dirname(__DIR__, 2);
        self::assertDirectoryExists($root . '/src/Modules/IdentitySessions/Application');
        self::assertDirectoryExists($root . '/src/Modules/IdentitySessions/Domain');
        self::assertDirectoryExists($root . '/src/Modules/IdentitySessions/Infrastructure');
        self::assertDirectoryExists($root . '/src/Modules/IdentitySessions/Interface');
        self::assertFileExists($root . '/src/Bootstrap/Module/IdentitySessionsModule.php');

        $repository = $this->read($root . '/src/Modules/IdentitySessions/Infrastructure/Persistence/'
            . 'MySqlIdentitySessionRepository.php');
        self::assertStringContainsString('final readonly class MySqlIdentitySessionRepository', $repository);
        self::assertStringContainsString('PDO::PARAM_LOB', $repository);
        self::assertStringContainsString('version = :version', $repository);
        self::assertStringNotContainsString('$_SESSION', $repository);
    }

    public function testCookiesContainOpaqueSelectorSecretValuesAndNeverSerialize(): void
    {
        $root = dirname(__DIR__, 2) . '/src/Modules/IdentitySessions/Domain/';
        foreach (['SessionCookieValue.php', 'DeviceCookieValue.php'] as $file) {
            $source = $this->read($root . $file);
            self::assertStringContainsString("return 'v1.'", $source);
            self::assertStringContainsString("'[REDACTED]'", $source);
            self::assertStringContainsString('jsonSerialize(): never', $source);
        }

        foreach (['SessionTokenSecret.php', 'DeviceTokenSecret.php'] as $file) {
            $source = $this->read($root . $file);
            self::assertStringContainsString("hash('sha256'", $source);
        }
        foreach (['SessionTokenHash.php', 'DeviceTokenHash.php'] as $file) {
            $source = $this->read($root . $file);
            self::assertStringContainsString('hash_equals', $source);
        }
    }

    public function testAuthenticationMiddlewarePrecedesRoutingAndHealthRoutesRemainStateless(): void
    {
        $root = dirname(__DIR__, 2);
        $module = $this->read($root . '/src/Bootstrap/Module/ApplicationHttpModule.php');
        self::assertMatchesRegularExpression(
            '/LocaleMiddleware::class[\s\S]+SessionAuthenticationMiddleware::class[\s\S]+RoutingRequestHandler::class/',
            $module,
        );
        $middleware = $this->read($root . '/src/Modules/IdentitySessions/Interface/Http/'
            . 'SessionAuthenticationMiddleware.php');
        self::assertStringContainsString("'/health/live'", $middleware);
        self::assertStringContainsString("'/health/ready'", $middleware);
    }

    public function testAccountSecurityViewsNeverOfferRemoteRevocationForCurrentResources(): void
    {
        $view = $this->read(dirname(__DIR__, 2)
            . '/resources/views/fragments/account-security-session-panel.php');
        self::assertStringContainsString("(\$session['current'] ?? false) !== true", $view);
        self::assertStringContainsString("(\$device['current'] ?? false) !== true", $view);
        self::assertStringContainsString('data-qmdb-modal', $view);
        self::assertStringNotContainsString('current_token_hash', $view);
        self::assertStringNotContainsString('accountInternalId', $view);
    }

    private function read(string $path): string
    {
        $contents = file_get_contents($path);
        self::assertIsString($contents);

        return $contents;
    }
}
