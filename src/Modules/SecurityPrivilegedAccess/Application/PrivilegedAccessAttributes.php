<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityPrivilegedAccess\Application;

final readonly class PrivilegedAccessAttributes
{
    public const string CONTEXT = 'qmdb.privileged_access_context';
    public const string WORKSPACE_CONTEXT = 'qmdb.privileged_workspace_context';

    private function __construct()
    {
    }
}
