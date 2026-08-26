<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Http;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Shared\Http\Contract\Controller;

final readonly class CallableController implements Controller
{
    /** @param Closure(ServerRequestInterface): ResponseInterface $handler */
    public function __construct(private Closure $handler)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return ($this->handler)($request);
    }
}
