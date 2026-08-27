<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityRecovery\Interface\Http;

use RuntimeException;

final class RecoveryFormInputException extends RuntimeException
{
    /** @param array<string, string> $errors */
    public function __construct(
        public readonly array $errors,
        public readonly string $safeEmail = '',
        public readonly string $safeToken = '',
        public readonly int $status = 422,
    ) {
        parent::__construct('Password recovery form input is invalid.');
    }
}
