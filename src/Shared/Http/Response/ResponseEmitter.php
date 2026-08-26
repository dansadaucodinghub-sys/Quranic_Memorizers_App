<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Response;

use Psr\Http\Message\ResponseInterface;

interface ResponseEmitter
{
    public function emit(ResponseInterface $response, string $requestMethod): void;
}
