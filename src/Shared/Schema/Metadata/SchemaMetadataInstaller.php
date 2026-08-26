<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Metadata;

use Qmdb\Shared\Schema\Connection\SchemaConnectionProvider;
use Qmdb\Shared\Schema\Exception\SchemaException;
use Qmdb\Shared\Schema\Lock\SchemaMutationLock;
use Throwable;

final readonly class SchemaMetadataInstaller implements SchemaMetadataInstallation
{
    public function __construct(
        private SchemaConnectionProvider $provider,
        private SchemaMutationLock $lockManager,
        private SchemaMetadataVerifier $verifier,
    ) {
    }

    public function install(): SchemaMetadataReport
    {
        $lock = $this->lockManager->acquire();
        try {
            return $this->installWithinLock();
        } finally {
            $this->lockManager->release($lock);
        }
    }

    public function installWithinLock(): SchemaMetadataReport
    {
        $existing = $this->verifier->verify();
        if ($existing->isReady()) {
            return $existing;
        }
        if ($existing->status !== SchemaMetadataStatus::NOT_INSTALLED) {
            throw new SchemaException(
                $existing->safeCode,
                'Existing schema metadata cannot be repaired automatically.',
            );
        }
        try {
            $connection = $this->provider->connection();
            foreach (SchemaMetadataDefinition::statements() as $statement) {
                $connection->exec($statement);
            }
            $statement = $connection->prepare(
                'INSERT INTO qmdb_schema_meta (meta_key, meta_value, updated_at) '
                . 'VALUES (:meta_key, :meta_value, UTC_TIMESTAMP(6)) '
                . 'ON DUPLICATE KEY UPDATE updated_at = updated_at',
            );
            $statement->execute([
                ':meta_key' => 'ledger_version',
                ':meta_value' => SchemaMetadataDefinition::LEDGER_VERSION,
            ]);
        } catch (Throwable $exception) {
            throw new SchemaException('SCHEMA_INSTALL_FAILED', 'Schema metadata installation failed.', $exception);
        }
        $report = $this->verifier->verify();
        if (!$report->isReady()) {
            throw new SchemaException($report->safeCode, 'Schema metadata verification failed.');
        }

        return $report;
    }
}
