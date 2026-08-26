<?php

declare(strict_types=1);

namespace Qmdb\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class DatabaseSecurityTest extends TestCase
{
    public function testConnectionFactoryHardensPdo(): void
    {
        $source = $this->source('src/Shared/Infrastructure/Persistence/MySql/Connection/MySqlConnectionFactory.php');

        self::assertStringContainsString('PDO::ATTR_EMULATE_PREPARES => false', $source);
        self::assertStringContainsString('PDO::ATTR_PERSISTENT => false', $source);
        self::assertStringContainsString('\\Pdo\\Mysql::ATTR_MULTI_STATEMENTS => false', $source);
        self::assertStringContainsString('PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION', $source);
    }

    public function testPasswordIsResolvedOnlyAtConnectionBoundary(): void
    {
        $configuration = $this->source('src/Shared/Configuration/Database/DatabaseConfiguration.php');
        $factory = $this->source(
            'src/Shared/Infrastructure/Persistence/MySql/Connection/MySqlConnectionFactory.php',
        );

        self::assertStringNotContainsString('password', strtolower($configuration));
        self::assertStringContainsString("SecretName::fromString('DB_PASSWORD')", $factory);
    }

    public function testReadinessPublishesOnlyBinaryPublicStatus(): void
    {
        $controller = $this->source('src/Shared/Http/Controller/ReadinessController.php');
        self::assertStringContainsString("'ready' : 'not_ready'", $controller);
        foreach (['host()', 'databaseName()', 'username()', 'getMessage()'] as $detail) {
            self::assertStringNotContainsString($detail, $controller);
        }
    }

    public function testSessionInitializerUsesOnlyStaticApplicationSql(): void
    {
        $source = $this->source(
            'src/Shared/Infrastructure/Persistence/MySql/Connection/MySqlSessionInitializer.php',
        );
        self::assertStringContainsString("time_zone = '+00:00'", $source);
        self::assertStringContainsString('SET NAMES utf8mb4', $source);
        self::assertStringNotContainsString('FOREIGN_KEY_CHECKS', $source);
        self::assertStringNotContainsString('SET GLOBAL', $source);
    }

    public function testSavepointNamesCannotContainClientInput(): void
    {
        $source = $this->source(
            'src/Shared/Infrastructure/Persistence/MySql/Transaction/PdoMySqlTransactionDriver.php',
        );
        self::assertStringContainsString("'/\\Aqmdb_sp_", $source);
    }

    public function testRetryClassifierIsNarrow(): void
    {
        $source = $this->source(
            'src/Shared/Infrastructure/Persistence/MySql/Transaction/'
            . 'MySqlRetryableTransactionFailureClassifier.php',
        );
        self::assertStringContainsString("=== '40001'", $source);
        self::assertStringContainsString('=== 1213', $source);
        self::assertStringNotContainsString('1205', $source);
    }

    public function testNoCredentialAppearsInExampleConfiguration(): void
    {
        $source = $this->source('.env.example');
        self::assertMatchesRegularExpression('/^DB_PASSWORD=\R/m', $source);
        self::assertMatchesRegularExpression('/^DB_SCHEMA_PASSWORD=\R/m', $source);
    }

    private function source(string $relativePath): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $relativePath);
        self::assertIsString($source);

        return $source;
    }
}
