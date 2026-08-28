<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Application\Exception;

use Qmdb\Shared\Http\Contract\SafeHttpException;
use RuntimeException;
use Throwable;

final class AuthorizationDeniedException extends RuntimeException implements SafeHttpException
{
    public const string SAFE_CODE = 'AUTHORIZATION_DENIED';

    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('The requested operation is not permitted.', 0, $previous);
    }

    public function safeCode(): string
    {
        return self::SAFE_CODE;
    }

    public function statusCode(): int
    {
        return 403;
    }
}
