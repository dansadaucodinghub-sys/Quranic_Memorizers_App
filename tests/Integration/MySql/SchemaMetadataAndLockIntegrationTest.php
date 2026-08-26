<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\MySql;

use PDO;
use Qmdb\Shared\Schema\Exception\SchemaException;
use Qmdb\Shared\Schema\Lock\SchemaLockManager;
use Qmdb\Shared\Schema\Metadata\SchemaMetadataDefinition;
use Qmdb\Shared\Schema\Metadata\SchemaMetadataInstaller;
use Qmdb\Shared\Schema\Metadata\SchemaMetadataVerifier;
use Qmdb\Tests\Support\MySql\SchemaMySqlIntegrationTestCase;

final class SchemaMetadataAndLockIntegrationTest extends SchemaMySqlIntegrationTestCase
{
    public function testMetadataInstallationIsIdempotentAndCreatesOnlyLedgerTables(): void
    {
        $provider = $this->schemaProvider();
        $lock = new SchemaLockManager($provider, $this->schemaConfiguration());
        $verifier = new SchemaMetadataVerifier($provider);
        $installer = new SchemaMetadataInstaller($provider, $lock, $verifier);

        self::assertTrue($installer->install()->isReady());
        self::assertTrue($installer->install()->isReady());
        self::assertTrue($verifier->verify()->isReady());

        $statement = $provider->connection()->prepare(
            'SELECT TABLE_NAME FROM information_schema.TABLES '
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'qmdb_schema_%' ORDER BY TABLE_NAME",
        );
        $statement->execute();
        $expected = SchemaMetadataDefinition::tableNames();
        sort($expected, SORT_STRING);
        self::assertSame($expected, $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function testNamedLockExcludesASecondSchemaConnectionAndReleases(): void
    {
        $configuration = $this->schemaConfiguration(1);
        $first = new SchemaLockManager($this->schemaProvider($configuration), $configuration);
        $second = new SchemaLockManager($this->schemaProvider($configuration), $configuration);
        $handle = $first->acquire();
        try {
            try {
                $second->acquire();
                self::fail('The second schema connection unexpectedly acquired the named lock.');
            } catch (SchemaException $exception) {
                self::assertSame('SCHEMA_LOCK_TIMEOUT', $exception->safeCode());
            }
        } finally {
            $first->release($handle);
        }

        $secondHandle = $second->acquire();
        $second->release($secondHandle);
        self::assertTrue($secondHandle->isReleased());
    }
}
