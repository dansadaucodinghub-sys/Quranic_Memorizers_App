<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Application;

final readonly class TenantContextAttributes
{
    public const string CONTEXT = 'qmdb.tenant_context';
    public const string VERSION = 'qmdb.tenant_context_version';

    private function __construct()
    {
    }
}
