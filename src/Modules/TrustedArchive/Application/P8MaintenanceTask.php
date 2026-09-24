<?php

declare(strict_types=1);

namespace Qmdb\Modules\TrustedArchive\Application;

use Qmdb\Modules\RecordPassport\Infrastructure\Persistence\RecordPassportProjector;
use Qmdb\Modules\TrustedArchive\Infrastructure\Persistence\TrustedArchiveSealer;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskExecutionContext;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskHandler;

/** Closed, bounded P8 scheduler dispatch. Legacy import is deliberately not auto-approved. */
final readonly class P8MaintenanceTask implements ScheduledTaskHandler
{
    public function __construct(private RecordPassportProjector $passports, private TrustedArchiveSealer $archive)
    {
    }
    public function __invoke(ScheduledTaskExecutionContext $context): mixed
    {
        match ($context->taskId()->value()) {
            'record_passports.project' => $this->passports->projectIssuedCertificates(),
            'trusted_archive.seal_pending' => $this->archive->sealIssuedCertificates(),
            'trusted_archive.reconcile' => $this->archive->verifyChains(),
            default => throw new \LogicException('Unregistered P8 scheduler task cannot be dispatched.'),
        };
        return null;
    }
}
