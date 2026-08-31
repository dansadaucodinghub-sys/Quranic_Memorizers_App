<?php

declare(strict_types=1);

namespace Qmdb\Modules\Geography\Application;

use InvalidArgumentException;

final readonly class AdministrativeAreaSearchQuery
{
    public function __construct(public string $query)
    {
        $length = mb_strlen($query, 'UTF-8');
        if ($length < 2 || $length > 80 || str_contains($query, "\0") || !mb_check_encoding($query, 'UTF-8')) {
            throw new InvalidArgumentException('Geography search query is invalid.');
        }
    }
}
