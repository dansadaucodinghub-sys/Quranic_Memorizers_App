<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Interface\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\IdentityAccess\Application\Readiness\IdentityAccessReadinessCheck;
use Qmdb\Shared\Database\Health\DatabaseHealthCheck;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Message\JsonResponseFactory;
use Qmdb\Shared\Schema\Health\SchemaHealthCheck;

final readonly class ApplicationReadinessController implements Controller
{
    public function __construct(
        private JsonResponseFactory $responses,
        private DatabaseHealthCheck $database,
        private SchemaHealthCheck $schema,
        private IdentityAccessReadinessCheck $identity,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $ready = $this->database->check()->isReady()
            && $this->schema->check()->isReady()
            && $this->identity->isReady();

        return $this->responses->create(['status' => $ready ? 'ready' : 'not_ready'], $ready ? 200 : 503);
    }
}
