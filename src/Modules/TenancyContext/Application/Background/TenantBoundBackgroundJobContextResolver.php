<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Application\Background;

use Qmdb\Shared\Background\Job\BackgroundJob;
use Qmdb\Shared\Background\Job\BackgroundJobContextResolver;
use Qmdb\Shared\Background\Job\PermanentBackgroundJobFailure;
use Qmdb\Shared\Configuration\Logging\LogLevel;
use Qmdb\Shared\Observability\Logging\EventLogger;
use Qmdb\Shared\Observability\Logging\LogEventName;

final readonly class TenantBoundBackgroundJobContextResolver implements BackgroundJobContextResolver
{
    public function __construct(
        private TenantBoundBackgroundContextRepository $repository,
        private ?EventLogger $logger = null,
    ) {
    }

    public function supports(BackgroundJob $job): bool
    {
        return $job instanceof AccountTenantBoundBackgroundJob;
    }

    public function resolve(BackgroundJob $job): TenantBoundBackgroundJobExecutionContext
    {
        if (!$job instanceof AccountTenantBoundBackgroundJob) {
            throw new PermanentBackgroundJobFailure('Unsupported tenant-bound background job.');
        }
        $context = $this->repository->resolve($job);
        if ($context === null) {
            $this->logger?->log(
                LogLevel::ERROR,
                new LogEventName('tenancy.context.background.resolution.failed'),
                [
                    'account_public_id' => $job->accountId()->toString(),
                    'workspace_public_id' => $job->workspaceId()->toString(),
                    'membership_public_id' => $job->membershipId()->toString(),
                    'reason_code' => 'IDENTITY_OR_STATE_UNAVAILABLE',
                ],
            );
            throw new PermanentBackgroundJobFailure('Tenant-bound background context is unavailable.');
        }

        return $context;
    }
}
