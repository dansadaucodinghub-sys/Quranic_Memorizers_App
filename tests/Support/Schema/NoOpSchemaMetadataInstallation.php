<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Schema;

use Qmdb\Shared\Schema\Metadata\SchemaMetadataInstallation;
use Qmdb\Shared\Schema\Metadata\SchemaMetadataReport;
use Qmdb\Shared\Schema\Metadata\SchemaMetadataStatus;

final readonly class NoOpSchemaMetadataInstallation implements SchemaMetadataInstallation
{
    public function install(): SchemaMetadataReport
    {
        return $this->installWithinLock();
    }

    public function installWithinLock(): SchemaMetadataReport
    {
        return new SchemaMetadataReport(SchemaMetadataStatus::READY, 'SCHEMA_METADATA_READY');
    }
}
