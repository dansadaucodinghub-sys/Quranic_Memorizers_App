<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Message\JsonResponseFactory;
use Qmdb\Shared\Database\Health\DatabaseHealthCheck;
use Qmdb\Shared\Schema\Health\SchemaHealthCheck;

final readonly class ReadinessController implements Controller
{
    public function __construct(
        private JsonResponseFactory $responseFactory,
        private DatabaseHealthCheck $databaseHealthCheck,
        private ?SchemaHealthCheck $schemaHealthCheck = null,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $databaseReport = $this->databaseHealthCheck->check();
        $schemaReport = $databaseReport->isReady() ? $this->schemaHealthCheck?->check() : null;
        $ready = $databaseReport->isReady()
            && ($this->schemaHealthCheck === null || $schemaReport?->isReady() === true);

        return $this->responseFactory->create(
            ['status' => $ready ? 'ready' : 'not_ready'],
            $ready ? 200 : 503,
        );
    }
}
