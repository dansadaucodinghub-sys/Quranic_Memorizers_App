<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySecurityNotifications\Application\Readiness;

use Qmdb\Modules\IdentityAccess\Configuration\IdentityAccessConfiguration;
use Qmdb\Modules\IdentitySecurityNotifications\Configuration\SecurityNotificationConfiguration;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskMap;
use Qmdb\Shared\Schema\Health\SchemaHealthCheck;
use Qmdb\Shared\Security\Secrets\SecretName;
use Qmdb\Shared\Security\Secrets\SecretsProvider;
use Symfony\Component\Mailer\Transport;
use Throwable;

final readonly class IdentitySecurityNotificationReadinessCheck
{
    private const TASK_ID = 'identity.security_notifications.deliver';

    public function __construct(
        private SecurityNotificationConfiguration $configuration,
        private IdentityAccessConfiguration $identityAccess,
        private ScheduledTaskMap $tasks,
        private SecretsProvider $secrets,
        private SchemaHealthCheck $schema,
    ) {
    }

    public function isReady(): bool
    {
        try {
            if (
                $this->configuration->batchSize < 1
                || $this->configuration->maximumAttempts < 1
                || $this->configuration->leaseSeconds < 30
                || $this->configuration->retryBaseSeconds < 0
                || $this->configuration->retryMaximumSeconds < $this->configuration->retryBaseSeconds
                || !$this->schema->check()->isReady()
            ) {
                return false;
            }
            $registered = false;
            foreach ($this->tasks->tasks() as $task) {
                $registered = $registered || $task->id()->value() === self::TASK_ID;
            }
            if (!$registered) {
                return false;
            }
            $dsn = $this->secrets->get(SecretName::fromString('MAILER_DSN'));
            Transport::fromDsn($dsn->reveal());

            return !$this->identityAccess->productionLike || !str_starts_with(strtolower($dsn->reveal()), 'null:');
        } catch (Throwable) {
            return false;
        }
    }
}
