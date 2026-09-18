<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\MySql;

use PDO;
use Qmdb\Modules\MediaIngestion\Application\{MediaBlobStore, MediaStagingCleaner};
use Qmdb\Modules\MediaProcessing\Application\{MediaScanner, MediaProcessor};
use Qmdb\Modules\MediaProcessing\Infrastructure\Persistence\{MySqlMediaScanWorker, MySqlMediaProcessingWorker, MySqlMediaMaintenanceWorker};
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Tests\Support\MySql\{AuthorizationMySqlFixture, MySqlIntegrationTestCase};

/** Real MySQL leases, independent connections and deterministic failure barriers. No runtime injectors. */
final class MediaWorkersIntegrationTest extends MySqlIntegrationTestCase
{
    private \Qmdb\Shared\Infrastructure\Persistence\MySql\Connection\MySqlConnectionProvider $connections;
    private PDO $pdo;
    private int $workspace;
    private int $actor;
    private int $asset;
    private string $key;
    /** @var array<string,string> */
    private array $objects = [];
    private MediaBlobStore&MediaStagingCleaner $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connections = $this->provider();
        $this->pdo = $this->connections->connection();
        \Qmdb\Tests\Support\MySql\MediaMySqlFixture::ensure($this->pdo, dirname(__DIR__, 3));
        $fixture = new AuthorizationMySqlFixture($this->pdo, dirname(__DIR__, 3));
        [$this->workspace] = $fixture->workspace();
        [$this->actor] = $fixture->account();
        $id = UuidV7::generate();
        $this->key = 'quarantine/' . $id->toString() . '.bin';
        $this->objects = [$this->key => 'synthetic original'];
        $this->pdo->prepare("INSERT INTO media_assets (public_id,workspace_id,created_by_account_id,purpose_code,media_kind,status,original_storage_provider_code,original_storage_object_key,original_filename_safe,byte_size,original_sha256,created_at,updated_at) VALUES (?,?,?,'RECITATION_EVIDENCE','AUDIO','QUARANTINED','LOCAL_PRIVATE',?,'synthetic.wav',18,?,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))")->execute([$id->toBinary(), $this->workspace, $this->actor, $this->key, hash('sha256', 'synthetic original', true)]);
        $this->asset = (int) $this->pdo->lastInsertId();
        $this->storage = new class ($this->objects) implements MediaBlobStore, MediaStagingCleaner {
            /** @param array<string,string> $objects */
            public function __construct(private array &$objects)
            {
            }
            public function get(string $objectKey): string
            {
                return $this->objects[$objectKey] ?? throw new \RuntimeException('Synthetic unavailable blob');
            }
            public function putImmutable(string $objectKey, string $contents): void
            {
                if (isset($this->objects[$objectKey]) && $this->objects[$objectKey] !== $contents) {
                    throw new \DomainException('Immutable object');
                }
                $this->objects[$objectKey] = $contents;
            }
            public function removeStaging(string $objectKey, string $expectedSha256): bool
            {
                if (!str_starts_with($objectKey, 'staging/')) {
                    throw new \DomainException('Not staging');
                }
                if (!isset($this->objects[$objectKey])) {
                    return false;
                }
                if (!hash_equals($expectedSha256, hash('sha256', $this->objects[$objectKey], true))) {
                    throw new \DomainException('Checksum mismatch');
                }
                unset($this->objects[$objectKey]);
                return true;
            }
        };
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo, $this->asset)) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->pdo->prepare("UPDATE media_assets SET status='ARCHIVED' WHERE id=?")->execute([$this->asset]);
            $this->pdo->prepare("UPDATE media_processing_jobs SET status='DEAD',lease_expires_at=NULL WHERE asset_id=? AND status IN ('QUEUED','LEASED')")->execute([$this->asset]);
        }
        parent::tearDown();
    }

    private function job(string $type): void
    {
        $this->pdo->prepare("INSERT INTO media_processing_jobs (public_id,workspace_id,asset_id,job_type,available_at,created_at,updated_at) VALUES (?,?,?,?,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))")->execute([UuidV7::generate()->toBinary(), $this->workspace, $this->asset, $type]);
    }
    private function state(): string
    {
        $statement = $this->pdo->prepare('SELECT status FROM media_assets WHERE id=?');
        $statement->execute([$this->asset]);
        return (string) $statement->fetchColumn();
    }
    private function scans(MediaScanner $scanner): MySqlMediaScanWorker
    {
        return new MySqlMediaScanWorker($this->connections, $this->storage, $scanner);
    }
    private function scanner(): MediaScanner
    {
        $scanner = $this->createStub(MediaScanner::class);
        $scanner->method('scan')->willReturn(['clean' => true, 'engine' => 'SYNTHETIC', 'safe_code' => 'CLEAN']);
        return $scanner;
    }
    private function processing(): void
    {
        $this->pdo->prepare("UPDATE media_assets SET status='PROCESSING' WHERE id=?")->execute([$this->asset]);
        $this->job('PROCESS_AUDIO');
    }
    private function probe(): \Qmdb\Modules\MediaProcessing\Application\MediaProbe
    {
        $probe = $this->createStub(\Qmdb\Modules\MediaProcessing\Application\MediaProbe::class);
        $probe->method('inspect')->willReturn(['mime' => 'audio/wav', 'duration_ms' => 1000, 'width' => null, 'height' => null, 'codec' => 'pcm_s16le']);
        return $probe;
    }

    public function testCleanScanQueuesExactlyOneProcessingJobAndReplayFindsNoWork(): void
    {
        $this->job('SCAN');
        self::assertSame('CLEAN', $this->scans($this->scanner())->processOne()['outcome']);
        self::assertSame('PROCESSING', $this->state());
        self::assertFalse($this->scans($this->scanner())->processOne()['claimed']);
        $count = $this->pdo->prepare("SELECT COUNT(*) FROM media_processing_jobs WHERE asset_id=? AND job_type='PROCESS_AUDIO'");
        $count->execute([$this->asset]);
        self::assertSame(1, (int) $count->fetchColumn());
    }
    public function testUnavailableScannerKeepsOriginalQuarantined(): void
    {
        $this->job('SCAN');
        $scanner = $this->createStub(MediaScanner::class);
        $scanner->method('scan')->willThrowException(new \RuntimeException('Test-only scanner failure'));
        self::assertSame('ERROR', $this->scans($scanner)->processOne()['outcome']);
        self::assertSame('QUARANTINED', $this->state());
        self::assertFalse($this->scans($scanner)->processOne()['claimed']);
    }
    public function testSecondConnectionCannotClaimAnActiveLease(): void
    {
        $this->job('SCAN');
        $scanner = $this->createStub(MediaScanner::class);
        $scanner->method('scan')->willReturnCallback(function (): array {
            $second = new MySqlMediaScanWorker($this->provider(), $this->storage, $this->scanner());
            self::assertFalse($second->processOne()['claimed']);
            return ['clean' => true, 'engine' => 'SYNTHETIC', 'safe_code' => 'CLEAN'];
        });
        self::assertSame('CLEAN', $this->scans($scanner)->processOne()['outcome']);
    }
    public function testExpiredAttemptCannotWriteAfterLeaseTakeover(): void
    {
        $this->job('SCAN');
        $scanner = $this->createStub(MediaScanner::class);
        $scanner->method('scan')->willReturnCallback(function (): array {
            $other = $this->provider();
            $other->connection()->prepare("UPDATE media_processing_jobs SET lease_expires_at=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 SECOND) WHERE asset_id=?")->execute([$this->asset]);
            self::assertSame('CLEAN', (new MySqlMediaScanWorker($other, $this->storage, $this->scanner()))->processOne()['outcome']);
            return ['clean' => true, 'engine' => 'SYNTHETIC', 'safe_code' => 'CLEAN'];
        });
        $this->expectException(\DomainException::class);
        $this->scans($scanner)->processOne();
    }
    public function testProcessingWritesPrivateImmutableVariantButNeverApproval(): void
    {
        $this->processing();
        $processor = $this->createStub(MediaProcessor::class);
        $processor->method('process')->willReturn('synthetic normalized');
        $worker = new MySqlMediaProcessingWorker($this->connections, $this->storage, $processor, $this->probe());
        self::assertSame('PROCESSED', $worker->processOne()['outcome']);
        self::assertSame('PENDING_MODERATION', $this->state());
        self::assertFalse($worker->processOne()['claimed']);
        $variant = $this->pdo->prepare('SELECT is_public_safe FROM media_variants WHERE asset_id=?');
        $variant->execute([$this->asset]);
        self::assertSame(0, (int) $variant->fetchColumn());
    }
    public function testProcessorFailureHasNoFalseVariantOrCompletionEvent(): void
    {
        $this->processing();
        $processor = $this->createStub(MediaProcessor::class);
        $processor->method('process')->willThrowException(new \RuntimeException('Test-only encoder failure'));
        self::assertSame('QUEUED', (new MySqlMediaProcessingWorker($this->connections, $this->storage, $processor, $this->probe()))->processOne()['outcome']);
        self::assertSame('PROCESSING', $this->state());
        $variants = $this->pdo->prepare('SELECT COUNT(*) FROM media_variants WHERE asset_id=?');
        $variants->execute([$this->asset]);
        self::assertSame(0, (int) $variants->fetchColumn());
        $events = $this->pdo->prepare("SELECT COUNT(*) FROM media_events WHERE asset_id=? AND event_code='media.processing.completed'");
        $events->execute([$this->asset]);
        self::assertSame(0, (int) $events->fetchColumn());
    }
    public function testIntegrityReconciliationWithdrawsCorruptionWithoutDeletingEvidence(): void
    {
        $this->objects[$this->key] = 'tampered';
        $worker = new MySqlMediaMaintenanceWorker($this->connections, $this->storage, $this->storage);
        self::assertSame(0, $worker->run('assets:reconcile', true)['changed']);
        self::assertSame('QUARANTINED', $this->state());
        self::assertSame(1, $worker->run('assets:reconcile')['changed']);
        self::assertSame('WITHDRAWN', $this->state());
        self::assertSame('tampered', $this->objects[$this->key]);
    }
    public function testExpiryAndCleanupRespectRetentionHoldsAndDryRun(): void
    {
        $this->pdo->prepare("UPDATE media_assets SET status='STAGING' WHERE id=?")->execute([$this->asset]);
        $this->pdo->prepare("INSERT INTO media_upload_sessions (public_id,workspace_id,asset_id,created_by_account_id,expected_byte_size,chunk_size,expires_at,created_at,updated_at) VALUES (?,?,?,?,262144,262144,DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 2 DAY),UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))")->execute([UuidV7::generate()->toBinary(), $this->workspace, $this->asset, $this->actor]);
        $session = (int) $this->pdo->lastInsertId();
        $key = 'staging/' . UuidV7::generate()->toString() . '.part';
        $this->objects[$key] = str_repeat('x', 262144);
        $this->pdo->prepare('INSERT INTO media_upload_parts (workspace_id,upload_session_id,part_number,byte_start,byte_end,byte_size,sha256,staging_object_key,created_at) VALUES (?,?,1,0,262143,262144,?,?,UTC_TIMESTAMP(6))')->execute([$this->workspace, $session, hash('sha256', $this->objects[$key], true), $key]);
        $worker = new MySqlMediaMaintenanceWorker($this->connections, $this->storage, $this->storage);
        self::assertSame(0, $worker->run('uploads:expire', true)['changed']);
        self::assertSame(1, $worker->run('uploads:expire')['changed']);
        self::assertSame(0, $worker->run('staging:cleanup')['changed']);
        $this->pdo->prepare('UPDATE media_upload_sessions SET updated_at=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 2 DAY) WHERE id=?')->execute([$session]);
        $this->pdo->prepare("INSERT INTO media_holds (workspace_id,asset_id,hold_code,placed_by_account_id,created_at) VALUES (?,?,'GOVERNANCE',?,UTC_TIMESTAMP(6))")->execute([$this->workspace, $this->asset, $this->actor]);
        self::assertSame(0, $worker->run('staging:cleanup')['changed']);
        $this->pdo->prepare('UPDATE media_holds SET released_at=UTC_TIMESTAMP(6),released_by_account_id=? WHERE asset_id=?')->execute([$this->actor, $this->asset]);
        self::assertSame(1, $worker->run('staging:cleanup')['changed']);
        self::assertArrayNotHasKey($key, $this->objects);
        self::assertSame(0, $worker->run('staging:cleanup')['changed']);
    }
}
