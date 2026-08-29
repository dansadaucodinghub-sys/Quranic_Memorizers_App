<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Application\Exception;

use Qmdb\Shared\Http\Contract\SafeHttpException;
use RuntimeException;

final class WorkspaceContextUnavailableException extends RuntimeException implements SafeHttpException
{
    public function __construct()
    {
        parent::__construct('The requested workspace is unavailable.');
    }

    public function statusCode(): int
    {
        return 404;
    }

    public function safeCode(): string
    {
        return 'TENANT_CONTEXT_UNAVAILABLE';
    }
}
