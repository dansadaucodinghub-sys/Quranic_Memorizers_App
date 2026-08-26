<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Schema;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;
use Qmdb\Shared\Schema\Seed\SeedId;
use Qmdb\Shared\Schema\Seed\SeedStepId;
use Qmdb\Shared\Schema\Seed\SqlSeedStep;

final class IdentifierAndSqlPolicyTest extends TestCase
{
    public function testCanonicalIdentifiersAreAccepted(): void
    {
        self::assertSame('20260825010101_create_example', (new MigrationId('20260825010101_create_example'))->value());
        self::assertSame('001_create_table', (new MigrationStepId('001_create_table'))->value());
        self::assertSame(1, (new MigrationStepId('001_create_table'))->sequence());
        self::assertSame('20260825010102_seed_example', (new SeedId('20260825010102_seed_example'))->value());
        self::assertSame('001_insert_row', (new SeedStepId('001_insert_row'))->value());
    }

    /** @return iterable<string, array{string}> */
    public static function invalidIdentifiers(): iterable
    {
        yield 'short timestamp' => ['20260825_invalid'];
        yield 'uppercase' => ['20260825010101_Invalid'];
        yield 'whitespace' => ['20260825010101_invalid value'];
        yield 'path syntax' => ['20260825010101_../invalid'];
        yield 'empty description' => ['20260825010101_'];
        yield 'too long' => ['20260825010101_' . str_repeat('a', 90)];
    }

    #[DataProvider('invalidIdentifiers')]
    public function testInvalidMigrationIdentifiersAreRejected(string $id): void
    {
        $this->expectException(InvalidArgumentException::class);
        new MigrationId($id);
    }

    #[DataProvider('invalidIdentifiers')]
    public function testInvalidSeedIdentifiersAreRejected(string $id): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SeedId($id);
    }

    public function testMigrationStepAcceptsOneControlledStatementAndCanonicalParameters(): void
    {
        $step = new SqlMigrationStep(
            new MigrationStepId('001_insert_row'),
            'Insert test row.',
            "INSERT INTO qmdb_test (id, label) VALUES (:id, :label)\r\n",
            [':label' => 'safe', ':id' => 1],
        );

        self::assertSame([':label' => 'safe', ':id' => 1], $step->parameters());
    }

    /** @return iterable<string, array{string}> */
    public static function prohibitedMigrationSql(): iterable
    {
        yield 'empty' => [''];
        yield 'multi statement' => ['CREATE TABLE a (id INT); DROP TABLE a'];
        yield 'global setting' => ['SET GLOBAL sql_mode = :mode'];
        yield 'foreign keys' => ['SET FOREIGN_KEY_CHECKS = 0'];
        yield 'grant' => ['GRANT SELECT ON qmdb.* TO someone'];
        yield 'user creation' => ["CREATE USER user IDENTIFIED BY 'secret'"];
        yield 'transaction control' => ['START TRANSACTION'];
        yield 'select' => ['SELECT 1'];
    }

    #[DataProvider('prohibitedMigrationSql')]
    public function testProhibitedMigrationSqlIsRejected(string $sql): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SqlMigrationStep(new MigrationStepId('001_invalid_sql'), 'Invalid operation.', $sql);
    }

    public function testUnsafeParameterNameIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SqlMigrationStep(
            new MigrationStepId('001_insert_row'),
            'Insert test row.',
            'INSERT INTO qmdb_test (id) VALUES (:id)',
            ['id' => 1],
        );
    }

    public function testNonScalarParameterIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SqlMigrationStep(
            new MigrationStepId('001_insert_row'),
            'Insert test row.',
            'INSERT INTO qmdb_test (id) VALUES (:id)',
            [':id' => ['not-scalar']],
        );
    }

    public function testSeedAllowsDml(): void
    {
        self::assertSame('INSERT INTO qmdb_test (id) VALUES (:id)', (new SqlSeedStep(
            new SeedStepId('001_insert_row'),
            'Insert row.',
            'INSERT INTO qmdb_test (id) VALUES (:id)',
            [':id' => 1],
        ))->sql());
        self::assertSame('UPDATE qmdb_test SET id = :id', (new SqlSeedStep(
            new SeedStepId('002_update_row'),
            'Update row.',
            'UPDATE qmdb_test SET id = :id',
            [':id' => 2],
        ))->sql());
        self::assertSame('DELETE FROM qmdb_test WHERE id = :id', (new SqlSeedStep(
            new SeedStepId('003_delete_row'),
            'Delete row.',
            'DELETE FROM qmdb_test WHERE id = :id',
            [':id' => 2],
        ))->sql());
    }

    /** @return iterable<string, array{string}> */
    public static function seedDdl(): iterable
    {
        yield 'create' => ['CREATE TABLE qmdb_test (id INT)'];
        yield 'alter' => ['ALTER TABLE qmdb_test ADD label VARCHAR(10)'];
        yield 'drop' => ['DROP TABLE qmdb_test'];
        yield 'truncate' => ['TRUNCATE TABLE qmdb_test'];
    }

    #[DataProvider('seedDdl')]
    public function testSeedRejectsDdl(string $sql): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SqlSeedStep(new SeedStepId('001_invalid_ddl'), 'Invalid seed DDL.', $sql);
    }
}
