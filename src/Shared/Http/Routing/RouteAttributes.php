<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Routing;

final class RouteAttributes
{
    public const NAME = 'qmdb.route.name';
    public const PARAMETERS = 'qmdb.route.parameters';
    public const METHOD = 'qmdb.route.method';
    public const VALIDATED_PATH = 'qmdb.request.validated_path';

    private function __construct()
    {
    }
}
