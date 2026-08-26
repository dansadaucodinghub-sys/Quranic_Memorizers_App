<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Metadata;

use Qmdb\Shared\Schema\Connection\RuntimeSchemaConnectionProvider;

final readonly class RuntimeSchemaMetadataVerifier implements SchemaMetadataInspection
{
    private SchemaMetadataVerifier $verifier;

    public function __construct(RuntimeSchemaConnectionProvider $provider)
    {
        $this->verifier = new SchemaMetadataVerifier($provider);
    }

    public function verify(): SchemaMetadataReport
    {
        return $this->verifier->verify();
    }
}
