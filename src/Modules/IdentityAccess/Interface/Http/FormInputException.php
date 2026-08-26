<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Interface\Http;

use RuntimeException;

final class FormInputException extends RuntimeException
{
    /** @param array<string, string> $errors */
    public function __construct(
        public readonly array $errors,
        public readonly string $safeEmail = '',
        public readonly int $status = 422,
    ) {
        parent::__construct('Identity form input is invalid.');
    }
}
