<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\MySql;

use DateTimeImmutable;
use PDO;
use PDOException;
use Qmdb\Modules\MediaCatalog\Infrastructure\Persistence\MySqlMediaEvidenceRepository;
use Qmdb\Modules\MediaModeration\Domain\{MediaGovernanceAction, MediaGovernanceRecord};
use Qmdb\Modules\MediaModeration\Infrastructure\Persistence\MySqlMediaGovernanceRepository;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Tests\Support\MySql\{AuthorizationMySqlFixture, MySqlIntegrationTestCase};

final class MediaGovernanceIntegrationTest extends MySqlIntegrationTestCase
{
    private \Qmdb\Shared\Infrastructure\Persistence\MySql\Connection\MySqlConnectionProvider $connections;
    private PDO $connection;
    private MySqlMediaGovernanceRepository $governance;
    private MySqlMediaEvidenceRepository $media;
    private int $workspace;
    private int $owner;
    private int $reviewer;
    private UuidV7 $assetId;
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $provider = $this->provider();
        $this->connections = $provider;
        $this->connection = $provider->connection();
        \Qmdb\Tests\Support\MySql\MediaMySqlFixture::ensure($this->connection, dirname(__DIR__, 3));
        $this->connection->beginTransaction();
        $this->seedFixture();
    }

    private function seedFixture(): void
    {
        $fixture = new AuthorizationMySqlFixture($this->connection, dirname(__DIR__, 3));
        [$this->workspace] = $fixture->workspace();
        [$this->owner] = $fixture->account();
        [$this->reviewer] = $fixture->account();
        $this->now = new DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->media = new MySqlMediaEvidenceRepository($this->connections);
        $this->governance = new MySqlMediaGovernanceRepository($this->connections);
        $asset = $this->media->createStaged($this->workspace, $this->owner, 'RECITATION_EVIDENCE', 'AUDIO', 'synthetic.wav', 'quarantine/' . UuidV7::generate()->toString() . '.bin', $this->now);
        $this->assetId = UuidV7::fromString($asset['public_id']);
        $this->media->ensurePrivateDeliveryPolicy($this->workspace, $asset['id'], $this->now);
        $this->connection->prepare("UPDATE media_assets SET status='PENDING_MODERATION' WHERE id=?")->execute([$asset['id']]);
    }

    protected function tearDown(): void
    {
        if (isset($this->connection) && $this->connection->inTransaction()) {
            $this->connection->rollBack();
        }
        parent::tearDown();
    }

    private function asset(): MediaGovernanceRecord
    {
        $asset = $this->governance->find($this->workspace, $this->assetId, true);
        self::assertNotNull($asset);
        return $asset;
    }

    private function reviewedEvidence(string $variant = 'NORMALIZED_V1'): void
    {
        $asset = $this->asset();
        $this->connection->prepare('UPDATE media_delivery_policies SET rights_granted=1,consent_granted=1 WHERE asset_id=?')->execute([$asset->id]);
        $this->connection->prepare("INSERT INTO media_scan_results (workspace_id,asset_id,result_code,engine_code,safe_detail_code,scanned_at,created_at) VALUES (?,?,'CLEAN','TEST_ONLY','CLEAN',UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))")->execute([$this->workspace,$asset->id]);
        $this->connection->prepare("INSERT INTO media_variants (public_id,workspace_id,asset_id,variant_code,storage_provider_code,storage_object_key,mime_type,byte_size,sha256,status,is_public_safe,created_at) VALUES (?,?,?,?,'LOCAL_PRIVATE',?,'audio/mpeg',4,?,'READY',0,UTC_TIMESTAMP(6))")->execute([UuidV7::generate()->toBinary(),$this->workspace,$asset->id,$variant,'variants/' . $this->assetId->toString() . '/test.mp3',hash('sha256', 'test', true)]);
    }

    private function mutate(MediaGovernanceAction $action, int $actor, string $hold = 'GOVERNANCE'): void
    {
        $asset = $this->asset();
        $target = $asset->target($action, $actor);
        $this->governance->apply($asset, $action, $target, $actor, $hold, $this->now);
        $this->governance->record(UuidV7::generate(), random_bytes(32), $asset, $action, $target, $actor, 'TEST_REVIEW', $this->now);
    }

    public function testApprovalThenHoldReleaseAndWithdrawalImmediatelyGateDelivery(): void
    {
        $this->reviewedEvidence();
        $this->mutate(MediaGovernanceAction::APPROVE, $this->reviewer);
        self::assertNotNull($this->media->findDeliverable($this->workspace, $this->assetId));
        $this->mutate(MediaGovernanceAction::HOLD, $this->reviewer);
        self::assertNull($this->media->findDeliverable($this->workspace, $this->assetId));
        $this->mutate(MediaGovernanceAction::RELEASE_HOLD, $this->reviewer);
        self::assertNotNull($this->media->findDeliverable($this->workspace, $this->assetId));
        $this->mutate(MediaGovernanceAction::WITHDRAW_CONSENT, $this->owner);
        self::assertSame('WITHDRAWN', $this->asset()->status);
        self::assertFalse($this->asset()->consent);
        self::assertNull($this->media->findDeliverable($this->workspace, $this->assetId));
        $this->mutate(MediaGovernanceAction::ARCHIVE, $this->reviewer);
        self::assertSame('ARCHIVED', $this->asset()->status);
    }

    public function testIndependentHoldCategoriesCannotReleaseEachOther(): void
    {
        $this->mutate(MediaGovernanceAction::HOLD, $this->reviewer, 'CONSENT');
        $this->mutate(MediaGovernanceAction::HOLD, $this->reviewer, 'SECURITY');
        $this->mutate(MediaGovernanceAction::RELEASE_HOLD, $this->reviewer, 'SECURITY');
        self::assertTrue($this->asset()->held);
        $this->mutate(MediaGovernanceAction::WITHDRAW_CONSENT, $this->owner);
        self::assertTrue($this->asset()->held);
        self::assertNull($this->media->findDeliverable($this->workspace, $this->assetId));
    }

    public function testOriginalReadyVariantCannotSatisfyProcessingForApproval(): void
    {
        $this->reviewedEvidence('ORIGINAL');
        self::assertFalse($this->asset()->processed);
        $this->expectException(\DomainException::class);
        $this->asset()->target(MediaGovernanceAction::APPROVE, $this->reviewer);
    }

    public function testCrossWorkspaceReadsAndDeliveryFailClosed(): void
    {
        [$other] = (new AuthorizationMySqlFixture($this->connection, dirname(__DIR__, 3)))->workspace();
        self::assertNull($this->governance->find($other, $this->assetId, true));
        self::assertSame([], $this->governance->recent($other));
        self::assertNull($this->media->findDeliverable($other, $this->assetId));
    }
    public function testConsentReviewPersistsProvenanceWithoutPublishingAndIsImmutable(): void
    {
        $evidence = new \Qmdb\Modules\MediaModeration\Domain\MediaConsentEvidence(UuidV7::generate(), hash('sha256', 'synthetic retained evidence'), 2, 2, 1, 1, true, true);
        $asset = $this->asset();
        self::assertSame('PENDING_MODERATION', $asset->target(MediaGovernanceAction::GRANT_CONSENT, $this->reviewer));
        $this->governance->grantConsent($asset, $evidence, $this->reviewer, $this->now);
        $this->mutate(MediaGovernanceAction::GRANT_CONSENT, $this->reviewer);
        self::assertTrue($this->asset()->consent);
        self::assertTrue($this->asset()->rights);
        self::assertSame('PENDING_MODERATION', $this->asset()->status);
        self::assertNull($this->media->findDeliverable($this->workspace, $this->assetId));
        $review = $this->connection->prepare('SELECT HEX(evidence_sha256) FROM media_consent_reviews WHERE asset_id=?');
        $review->execute([$asset->id]);
        self::assertSame(strtoupper($evidence->evidenceSha256), $review->fetchColumn());
        foreach (['UPDATE media_consent_reviews SET participant_count=3 WHERE asset_id=?', 'DELETE FROM media_consent_reviews WHERE asset_id=?'] as $sql) {
            try {
                $this->connection->prepare($sql)->execute([$asset->id]);
                self::fail('Consent review evidence cannot be replaced.');
            } catch (PDOException $error) {
                self::assertSame('45000', $error->getCode());
            }
        }
    }

    public function testReceiptReplayIsRequestBoundAndAppendOnly(): void
    {
        $asset = $this->asset();
        $submission = UuidV7::generate();
        $fingerprint = random_bytes(32);
        $this->governance->record($submission, $fingerprint, $asset, MediaGovernanceAction::REJECT, 'REJECTED', $this->reviewer, 'TEST_REVIEW', $this->now);
        self::assertSame(['asset_id' => $asset->publicId,'status' => 'REJECTED','version' => 2], $this->governance->replay($submission, $fingerprint));
        try {
            $this->governance->replay($submission, random_bytes(32));
            self::fail('A changed request must not replay.');
        } catch (\DomainException $error) {
            self::assertSame('Submission identifier was used for a different request.', $error->getMessage());
        }
        foreach (['UPDATE media_governance_operations SET reason_code=\'TAMPER\' WHERE submission_id=?','DELETE FROM media_governance_operations WHERE submission_id=?'] as $sql) {
            try {
                $this->connection->prepare($sql)->execute([$submission->toBinary()]);
                self::fail('Receipt tampering must fail.');
            } catch (PDOException $error) {
                self::assertSame('45000', $error->getCode());
            }
        }
    }

    public function testFailureAfterMutationRollsBackStateReceiptAndEvent(): void
    {
        $before = $this->asset();
        $this->connection->exec('SAVEPOINT p9_fault');
        try {
            $this->mutate(MediaGovernanceAction::REJECT, $this->reviewer);
            throw new \RuntimeException('Synthetic audit failure');
        } catch (\RuntimeException) {
            $this->connection->exec('ROLLBACK TO SAVEPOINT p9_fault');
        }
        self::assertEquals($before, $this->asset());
        $count = $this->connection->prepare('SELECT COUNT(*) FROM media_governance_operations WHERE asset_id=?');
        $count->execute([$before->id]);
        self::assertSame(0, (int) $count->fetchColumn());
        $events = $this->connection->prepare('SELECT COUNT(*) FROM media_events WHERE asset_id=?');
        $events->execute([$before->id]);
        self::assertSame(0, (int) $events->fetchColumn());
    }

    public function testConcurrentConnectionCannotBypassTheAggregateLock(): void
    {
        $asset = $this->asset();
        $other = $this->provider()->connection();
        $other->beginTransaction();
        try {
            $other->prepare('SELECT id FROM media_assets WHERE id=? FOR UPDATE NOWAIT')->execute([$asset->id]);
            self::fail('Concurrent media lock must fail immediately.');
        } catch (PDOException $error) {
            self::assertSame(3572, $error->errorInfo[1] ?? null);
        } finally {
            $other->rollBack();
        }
    }

    public function testStaleVersionCannotOverwriteAReview(): void
    {
        $old = $this->asset();
        $this->mutate(MediaGovernanceAction::REJECT, $this->reviewer);
        $this->expectException(\DomainException::class);
        $this->governance->apply($old, MediaGovernanceAction::REMOVE, 'WITHDRAWN', $this->reviewer, 'GOVERNANCE', $this->now);
    }

    public function testActualAuditFailureRollsBackTheMutationReceiptEventAndStepUpConsumption(): void
    {
        $this->applicationTransaction(true);
    }

    public function testServiceReplayUsesTheSameReceiptWithoutConsumingAnotherStepUpGrant(): void
    {
        $this->applicationTransaction(false);
    }

    private function applicationTransaction(bool $failAudit): void
    {
        $this->connection->rollBack();
        $manager = $this->transactionManager($this->connections);
        try {
            $manager->transactional(function () use ($manager, $failAudit): void {
                $this->seedFixture();
                $fixture = new AuthorizationMySqlFixture($this->connection, dirname(__DIR__, 3));
                [$reviewer,$reviewerId] = $fixture->account();
                $actor = $fixture->authenticatedStepUpContext($reviewer, $reviewerId, 'MEDIA_REJECT');
                [$membership,$membershipId] = $fixture->membership($this->workspace, $reviewer);
                $workspaceQuery = $this->connection->prepare('SELECT public_id FROM workspaces WHERE id=?');
                $workspaceQuery->execute([$this->workspace]);
                $workspaceBytes = $workspaceQuery->fetchColumn();
                self::assertIsString($workspaceBytes);
                $tenant = \Qmdb\Modules\TenancyContext\Domain\AccountWorkspaceTenantContext::trusted($reviewer, $reviewerId, $actor->sessionInternalId, $actor->sessionId, $this->workspace, \Qmdb\Modules\Tenancy\Domain\Value\WorkspaceId::fromBinary($workspaceBytes), \Qmdb\Modules\Tenancy\Domain\WorkspaceStatus::ACTIVE, 1, \Qmdb\Modules\TenancyContext\Domain\ResolvedWorkspaceMembershipIdentity::trusted($membership, $membershipId, $this->workspace, $reviewer, \Qmdb\Modules\Tenancy\Domain\MembershipStatus::ACTIVE, 1), 'Synthetic workspace', new \Qmdb\Modules\TenancyContext\Domain\TenantContextVersion(1), $actor->authenticatedAt);
                $clock = $this->createStub(\Qmdb\Shared\Time\Clock::class);
                $clock->method('now')->willReturn($actor->authenticatedAt);
                $limits = $this->createStub(\Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter::class);
                $limits->method('consume')->willReturn(\Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitDecision::allowed());
                $fingerprints = $this->createStub(\Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator::class);
                $fingerprints->method('generate')->willReturnCallback(static fn(string $domain, string $value): \Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprint => new \Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprint(hash('sha256', $domain . $value, true)));
                $audit = $this->createMock(\Qmdb\Modules\SecurityAudit\Application\SecurityAuditRecorder::class);
                $append = $audit->expects(self::once())->method('append');
                if ($failAudit) {
                    $append->willThrowException(new \RuntimeException('P9_TEST_AUDIT_FAILURE'));
                } else {
                    $append->willReturn(new \Qmdb\Modules\SecurityAudit\Application\SecurityAuditAppendResult(UuidV7::generate()->toString(), UuidV7::generate()->toString(), 1));
                }
                $service = new \Qmdb\Modules\MediaModeration\Application\MediaGovernanceService($this->governance, $this->createStub(\Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequirementGuard::class), new \Qmdb\Modules\IdentityMultiFactor\Application\StepUpGuard(new \Qmdb\Modules\IdentityMultiFactor\Infrastructure\Persistence\MySqlStepUpGrantRepository($this->connections), $clock), $limits, $fingerprints, new \Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender($audit), $manager, $clock);
                $submission = UuidV7::generate();
                $before = $this->asset();
                try {
                    $result = $service->execute($actor, $tenant, $submission, $this->assetId, $before->version, MediaGovernanceAction::REJECT, 'INDEPENDENT_REVIEW');
                    self::assertFalse($failAudit);
                    self::assertSame($result, $service->execute($actor, $tenant, $submission, $this->assetId, $before->version, MediaGovernanceAction::REJECT, 'INDEPENDENT_REVIEW'));
                    self::assertSame('REJECTED', $this->asset()->status);
                } catch (\RuntimeException $error) {
                    self::assertTrue($failAudit);
                    self::assertSame('P9_TEST_AUDIT_FAILURE', $error->getMessage());
                    self::assertEquals($before, $this->asset());
                }
                self::assertSame($failAudit ? 0 : 1, $fixture->consumedStepUpGrantCount($reviewer, 'MEDIA_REJECT'));
                foreach (['media_governance_operations','media_events'] as $table) {
                    $count = $this->connection->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE asset_id=?');
                    $count->execute([$before->id]);
                    self::assertSame($failAudit ? 0 : 1, (int)$count->fetchColumn());
                }
                throw new \DomainException('P9_FIXTURE_ROLLBACK');
            });
        } catch (\DomainException $error) {
            self::assertSame('P9_FIXTURE_ROLLBACK', $error->getMessage());
        }
    }
}
