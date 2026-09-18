<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Modules\MediaProcessing\Application\MediaRuntimeScheduledTask;
use Qmdb\Modules\MediaProcessing\Application\MediaScanWorker;
use Qmdb\Modules\MediaProcessing\Application\MediaProcessingWorker;
use Qmdb\Modules\MediaProcessing\Application\MediaScanner;
use Qmdb\Modules\MediaProcessing\Application\MediaProcessor;
use Qmdb\Modules\MediaProcessing\Application\MediaMaintenanceWorker;
use Qmdb\Modules\MediaIngestion\Application\MediaStagingCleaner;
use Qmdb\Modules\MediaIngestion\Application\MediaBlobStore;
use Qmdb\Modules\MediaProcessing\Infrastructure\Security\ClamAvMediaScanner;
use Qmdb\Modules\MediaProcessing\Infrastructure\Security\FailClosedMediaScanner;
use Qmdb\Modules\MediaProcessing\Infrastructure\Process\FfmpegMediaProcessor;
use Qmdb\Modules\MediaProcessing\Infrastructure\Process\FailClosedMediaProcessor;
use Qmdb\Shared\Background\Scheduler\FixedIntervalSchedule;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskId;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskRegistration;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceReference;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Configuration\EnvironmentVariables;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;

final readonly class MediaProcessingModule implements Module
{
    public function id(): ModuleId
    {
        return new ModuleId('media.processing');
    }
    public function dependencies(): array
    {
        return [new ModuleId('media.ingestion'), new ModuleId('foundation.core'), new ModuleId('foundation.application'), new ModuleId('foundation.database')];
    }
    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(\Qmdb\Modules\MediaProcessing\Application\MediaProbe::class, 'media.processing', [EnvironmentVariables::class, MediaBlobStore::class], new ClosureServiceFactory(static function (DependencyResolver $resolver): \Qmdb\Modules\MediaProcessing\Application\MediaProbe {
            $binary = ServiceReference::get($resolver, EnvironmentVariables::class)->optionalString('QMDB_MEDIA_FFPROBE_BINARY');
            return $binary !== null && is_file($binary) ? new \Qmdb\Modules\MediaProcessing\Infrastructure\Probe\FfprobeMediaProbe(ServiceReference::get($resolver, MediaBlobStore::class), $binary) : new \Qmdb\Modules\MediaProcessing\Infrastructure\Probe\UnavailableMediaProbe();
        })));
        $context->service(ServiceDefinition::factory(\Qmdb\Modules\MediaProcessing\Application\MediaRuntimeReadiness::class, 'media.processing', [MediaScanner::class, MediaProcessor::class, EnvironmentVariables::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): \Qmdb\Modules\MediaProcessing\Application\MediaRuntimeReadiness => new \Qmdb\Modules\MediaProcessing\Infrastructure\Process\ConfiguredMediaRuntimeReadiness(ServiceReference::get($resolver, MediaScanner::class), ServiceReference::get($resolver, MediaProcessor::class), ServiceReference::get($resolver, EnvironmentVariables::class)->optionalString('QMDB_MEDIA_FFPROBE_BINARY')))));
        $context->service(ServiceDefinition::factory(MediaScanner::class, 'media.processing', [EnvironmentVariables::class], new ClosureServiceFactory(static function (DependencyResolver $resolver): MediaScanner {
            $binary = ServiceReference::get($resolver, EnvironmentVariables::class)->optionalString('QMDB_MEDIA_SCANNER_BINARY');
            $timeout = new \Qmdb\Modules\MediaProcessing\Domain\MediaScanTimeout(ServiceReference::get($resolver, EnvironmentVariables::class)->optionalString('QMDB_MEDIA_SCAN_TIMEOUT_SECONDS'));
            return is_string($binary) && $binary !== '' && is_file($binary) ? new ClamAvMediaScanner($binary, $timeout->seconds, ServiceReference::get($resolver, EnvironmentVariables::class)->optionalString('QMDB_MEDIA_SCANNER_DATABASE')) : new FailClosedMediaScanner();
        })));
        $context->service(ServiceDefinition::factory(MediaProcessor::class, 'media.processing', [EnvironmentVariables::class], new ClosureServiceFactory(static function (DependencyResolver $resolver): MediaProcessor {
            $binary = ServiceReference::get($resolver, EnvironmentVariables::class)->optionalString('QMDB_MEDIA_FFMPEG_BINARY');
            return is_string($binary) && $binary !== '' && is_file($binary) ? new FfmpegMediaProcessor($binary) : new FailClosedMediaProcessor();
        })));
        $context->service(ServiceDefinition::factory(MediaScanWorker::class, 'media.processing', [DatabaseConnectionProvider::class, MediaBlobStore::class, MediaScanner::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): MediaScanWorker => new \Qmdb\Modules\MediaProcessing\Infrastructure\Persistence\MySqlMediaScanWorker(ServiceReference::get($resolver, DatabaseConnectionProvider::class), ServiceReference::get($resolver, MediaBlobStore::class), ServiceReference::get($resolver, MediaScanner::class)))));
        $context->service(ServiceDefinition::factory(MediaProcessingWorker::class, 'media.processing', [DatabaseConnectionProvider::class, MediaBlobStore::class, MediaProcessor::class, \Qmdb\Modules\MediaProcessing\Application\MediaProbe::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): MediaProcessingWorker => new \Qmdb\Modules\MediaProcessing\Infrastructure\Persistence\MySqlMediaProcessingWorker(ServiceReference::get($resolver, DatabaseConnectionProvider::class), ServiceReference::get($resolver, MediaBlobStore::class), ServiceReference::get($resolver, MediaProcessor::class), ServiceReference::get($resolver, \Qmdb\Modules\MediaProcessing\Application\MediaProbe::class)))));
        $context->service(ServiceDefinition::factory(MediaMaintenanceWorker::class, 'media.processing', [DatabaseConnectionProvider::class, MediaBlobStore::class, MediaStagingCleaner::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): MediaMaintenanceWorker => new \Qmdb\Modules\MediaProcessing\Infrastructure\Persistence\MySqlMediaMaintenanceWorker(ServiceReference::get($resolver, DatabaseConnectionProvider::class), ServiceReference::get($resolver, MediaBlobStore::class), ServiceReference::get($resolver, MediaStagingCleaner::class)))));
        $context->service(ServiceDefinition::factory(MediaRuntimeScheduledTask::class, 'media.processing', [MediaScanWorker::class, MediaProcessingWorker::class, MediaMaintenanceWorker::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): MediaRuntimeScheduledTask => new MediaRuntimeScheduledTask(ServiceReference::get($resolver, MediaScanWorker::class), ServiceReference::get($resolver, MediaProcessingWorker::class), ServiceReference::get($resolver, MediaMaintenanceWorker::class)))));
        foreach (
            [
            ['media.scans.process', 'Claim bounded quarantined P9 scan jobs.', 60],
            ['media.processing.process', 'Claim bounded clean P9 media processing jobs.', 60],
            ['media.staging.cleanup', 'Remove only expired P9 staging artifacts.', 900],
            ['media.uploads.expire', 'Expire bounded incomplete P9 upload sessions.', 300],
            ['media.assets.reconcile', 'Reconcile bounded P9 media evidence state.', 900],
            ] as [$id, $description, $interval]
        ) {
            $leaseSeconds = in_array($id, ['media.scans.process', 'media.processing.process'], true) ? 600 : 120;
            $context->scheduledTask(new ScheduledTaskRegistration(new ScheduledTaskId($id), $description, new FixedIntervalSchedule($interval), MediaRuntimeScheduledTask::class, $leaseSeconds, 'media.processing'));
        }
    }
}
