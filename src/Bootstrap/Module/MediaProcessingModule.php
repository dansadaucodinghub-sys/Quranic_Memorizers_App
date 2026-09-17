<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Modules\MediaProcessing\Application\MediaRuntimeScheduledTask;
use Qmdb\Modules\MediaProcessing\Application\MediaScanWorker;
use Qmdb\Modules\MediaProcessing\Application\MediaProcessingWorker;
use Qmdb\Modules\MediaProcessing\Application\MediaScanner;
use Qmdb\Modules\MediaProcessing\Application\MediaProcessor;
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
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;

final readonly class MediaProcessingModule implements Module
{
    public function id(): ModuleId { return new ModuleId('media.processing'); }
    public function dependencies(): array { return [new ModuleId('media.ingestion'), new ModuleId('foundation.application'), new ModuleId('foundation.database')]; }
    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(MediaScanner::class, 'media.processing', [], new ClosureServiceFactory(static function (DependencyResolver $resolver): MediaScanner {
            $binary=getenv('QMDB_MEDIA_SCANNER_BINARY');
            return is_string($binary)&&$binary!==''&&is_file($binary)?new ClamAvMediaScanner($binary):new FailClosedMediaScanner();
        })));
        $context->service(ServiceDefinition::factory(MediaProcessor::class, 'media.processing', [], new ClosureServiceFactory(static function (DependencyResolver $resolver): MediaProcessor {
            $binary=getenv('QMDB_MEDIA_FFMPEG_BINARY');
            return is_string($binary)&&$binary!==''&&is_file($binary)?new FfmpegMediaProcessor($binary):new FailClosedMediaProcessor();
        })));
        $context->service(ServiceDefinition::factory(MediaScanWorker::class, 'media.processing', [DatabaseConnectionProvider::class, MediaBlobStore::class, MediaScanner::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): MediaScanWorker => new MediaScanWorker(ServiceReference::get($resolver, DatabaseConnectionProvider::class), ServiceReference::get($resolver, MediaBlobStore::class), ServiceReference::get($resolver, MediaScanner::class)))));
        $context->service(ServiceDefinition::factory(MediaProcessingWorker::class, 'media.processing', [DatabaseConnectionProvider::class, MediaBlobStore::class, MediaProcessor::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): MediaProcessingWorker => new MediaProcessingWorker(ServiceReference::get($resolver, DatabaseConnectionProvider::class), ServiceReference::get($resolver, MediaBlobStore::class), ServiceReference::get($resolver, MediaProcessor::class)))));
        $context->service(ServiceDefinition::factory(MediaRuntimeScheduledTask::class, 'media.processing', [MediaScanWorker::class, MediaProcessingWorker::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): MediaRuntimeScheduledTask => new MediaRuntimeScheduledTask(ServiceReference::get($resolver, MediaScanWorker::class), ServiceReference::get($resolver, MediaProcessingWorker::class)))));
        foreach ([
            ['media.scans.process', 'Claim bounded quarantined P9 scan jobs.', 60],
            ['media.processing.process', 'Claim bounded clean P9 media processing jobs.', 60],
            ['media.staging.cleanup', 'Remove only expired P9 staging artifacts.', 900],
            ['media.uploads.expire', 'Expire bounded incomplete P9 upload sessions.', 300],
            ['media.assets.reconcile', 'Reconcile bounded P9 media evidence state.', 900],
        ] as [$id, $description, $interval]) {
            $context->scheduledTask(new ScheduledTaskRegistration(new ScheduledTaskId($id), $description, new FixedIntervalSchedule($interval), MediaRuntimeScheduledTask::class, 120, 'media.processing'));
        }
    }
}
