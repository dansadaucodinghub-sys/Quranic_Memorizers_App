<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Metadata;

interface SchemaMetadataInstallation
{
    public function install(): SchemaMetadataReport;

    public function installWithinLock(): SchemaMetadataReport;
}
