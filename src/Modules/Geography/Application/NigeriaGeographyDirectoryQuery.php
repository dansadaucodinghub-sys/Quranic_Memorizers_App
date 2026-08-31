<?php

declare(strict_types=1);

namespace Qmdb\Modules\Geography\Application;

use InvalidArgumentException;

final readonly class NigeriaGeographyDirectoryQuery
{
    public function __construct(public string $search = '')
    {
        if (strlen($search) > 80 || str_contains($search, "\0") || !mb_check_encoding($search, 'UTF-8')) {
            throw new InvalidArgumentException('Geography search query is invalid.');
        }
    }
}
