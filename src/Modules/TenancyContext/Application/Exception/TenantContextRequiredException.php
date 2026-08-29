<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Application\Exception;

use Qmdb\Shared\Http\Contract\SafeHttpException;
use RuntimeException;

final class TenantContextRequiredException extends RuntimeException implements SafeHttpException
{
    public function __construct()
    {
        parent::__construct('An active workspace selection is required.');
    }

    public function statusCode(): int
    {
        return 409;
    }

    public function safeCode(): string
    {
        return 'TENANT_CONTEXT_REQUIRED';
    }
}
