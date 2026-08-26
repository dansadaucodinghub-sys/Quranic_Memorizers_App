<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Metadata;

interface SchemaMetadataInspection
{
    public function verify(): SchemaMetadataReport;
}
