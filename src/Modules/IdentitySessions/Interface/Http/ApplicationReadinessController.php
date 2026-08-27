<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Interface\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\IdentityAccess\Application\Readiness\IdentityAccessReadinessCheck;
use Qmdb\Modules\IdentityRecovery\Application\Readiness\IdentityRecoveryReadinessCheck;
use Qmdb\Modules\IdentitySecurityNotifications\Application\Readiness\IdentitySecurityNotificationReadinessCheck;
use Qmdb\Modules\IdentitySessions\Application\Readiness\IdentitySessionReadinessCheck;
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
        private IdentitySessionReadinessCheck $sessions,
        private IdentityRecoveryReadinessCheck $recovery,
        private IdentitySecurityNotificationReadinessCheck $notifications,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $ready = $this->database->check()->isReady()
            && $this->schema->check()->isReady()
            && $this->identity->isReady()
            && $this->sessions->isReady()
            && $this->recovery->isReady()
            && $this->notifications->isReady();

        return $this->responses->create(['status' => $ready ? 'ready' : 'not_ready'], $ready ? 200 : 503);
    }
}
