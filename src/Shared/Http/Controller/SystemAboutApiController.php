<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Message\JsonResponseFactory;

final readonly class SystemAboutApiController implements Controller
{
    public function __construct(
        private SystemPresentationDataProvider $data,
        private JsonResponseFactory $responses,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->responses->create([...$this->data->information(), 'status' => 'ready']);
    }
}
