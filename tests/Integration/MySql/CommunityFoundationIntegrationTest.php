<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\MySql;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Qmdb\Bootstrap\ApplicationFactory;
use Qmdb\Modules\Community\Infrastructure\Persistence\MySqlCommunityOperationReceipts;
use Qmdb\Modules\Community\Infrastructure\Persistence\MySqlCommunityProfileRepository;
use Qmdb\Modules\Community\Infrastructure\Persistence\MySqlRecitationClipRepository;
use Qmdb\Modules\Community\Infrastructure\Persistence\MySqlCommunityReportRepository;
use Qmdb\Modules\Community\Infrastructure\Persistence\MySqlCommunityModerationRepository;
use Qmdb\Modules\Community\Infrastructure\Persistence\MySqlCommunityModerationAppealRepository;
use Qmdb\Modules\Community\Infrastructure\Persistence\MySqlCommunityNotificationIntentRepository;
use Qmdb\Modules\Community\Infrastructure\Persistence\MySqlCommunityPublicClipReader;
use Qmdb\Modules\Community\Infrastructure\Persistence\MySqlCommunitySocialRepository;
use Qmdb\Modules\Community\Infrastructure\Persistence\MySqlCommunityGlobalReceipts;
use Qmdb\Modules\Community\Infrastructure\Persistence\MySqlCommunityInteractionRepository;
use Qmdb\Modules\Community\Infrastructure\Persistence\MySqlCommunityCommentRepository;
use Qmdb\Modules\Community\Infrastructure\Persistence\MySqlCommunityFeedCandidates;
use Qmdb\Modules\Community\Infrastructure\Persistence\MySqlCommunityParticipantEligibility;
use Qmdb\Modules\Community\Domain\ClipStatus;
use Qmdb\Modules\Identity\Infrastructure\Security\SodiumContactCipher;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Tests\Support\MySql\AuthorizationMySqlFixture;
use Qmdb\Tests\Support\MySql\MySqlIntegrationTestCase;
use Qmdb\Tests\Support\Http\HttpTestFactory;

/** Runs against the isolated, fully migrated test database; never production data. */
final class CommunityFoundationIntegrationTest extends MySqlIntegrationTestCase
{
    private PDO $database;
    private \Qmdb\Shared\Infrastructure\Persistence\MySql\Connection\MySqlConnectionProvider $connections;
    private int $workspaceId;
    private int $accountId;
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connections = $this->provider();
        $this->database = $this->connections->connection();
        $tables = $this->database->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('recitation_clips','community_profiles','community_reports','community_operation_receipts')");
        self::assertNotFalse($tables);
        self::assertSame(4, (int) $tables->fetchColumn(), 'P10 migrations must be installed in the dedicated test database.');
        $this->database->beginTransaction();
        $fixture = new AuthorizationMySqlFixture($this->database, dirname(__DIR__, 3));
        [$this->workspaceId] = $fixture->workspace();
        [$this->accountId] = $fixture->account();
        $this->now = new DateTimeImmutable('2026-09-22T12:00:00.000000Z');
    }

    protected function tearDown(): void
    {
        if (isset($this->database) && $this->database->inTransaction()) {
            $this->database->rollBack();
        }
        parent::tearDown();
    }

    public function testPrivateProfileRequiresSelfLinkAndAdultConsentForPublicVisibility(): void
    {
        $profiles = new MySqlCommunityProfileRepository($this->connections);
        $this->expectException(\DomainException::class);
        $profiles->createPrivate($this->accountId, 'Test Reciter', $this->now);
    }

    public function testCommunityParticipationFailsClosedForUnlinkedMinorOrSuspendedAccount(): void
    {
        $eligibility = new MySqlCommunityParticipantEligibility($this->connections);
        try {
            $eligibility->requireAdultSelfLinkedAccount($this->accountId);
            self::fail('An unlinked account must not participate.');
        } catch (\DomainException) {
            self::addToAssertionCount(1);
        }
        $personId = $this->selfLink($this->accountId, '1990-01-01');
        $eligibility->requireAdultSelfLinkedAccount($this->accountId);
        self::addToAssertionCount(1);
        $birth = $this->database->prepare('UPDATE people_persons SET birth_date=:birth WHERE id=:person');
        $birth->execute(['birth' => '2012-01-01', 'person' => $personId]);
        try {
            $eligibility->requireAdultSelfLinkedAccount($this->accountId);
            self::fail('A minor account must not add social content.');
        } catch (\DomainException) {
            self::addToAssertionCount(1);
        }
        $birth->execute(['birth' => '1990-01-01', 'person' => $personId]);
        $this->database->prepare("UPDATE user_accounts SET account_status='SUSPENDED' WHERE id=:account")
            ->execute(['account' => $this->accountId]);
        $this->expectException(\DomainException::class);
        $eligibility->requireAdultSelfLinkedAccount($this->accountId);
    }

    public function testAdultProfileVisibilityIsVersionedAndEventsAreImmutable(): void
    {
        $this->selfLink($this->accountId, '1990-01-01');
        $profiles = new MySqlCommunityProfileRepository($this->connections);
        $created = $profiles->createPrivate($this->accountId, 'Test Reciter', $this->now);
        self::assertSame('PRIVATE', $created['visibility']);
        $published = $profiles->changeVisibility(
            $this->accountId,
            UuidV7::fromString($created['public_id']),
            1,
            'PUBLIC',
            $this->now
        );
        self::assertSame(['public_id' => $created['public_id'], 'visibility' => 'PUBLIC', 'version' => 2], $published);
        $rows = $this->database->query('SELECT COUNT(*) FROM community_profile_events');
        self::assertNotFalse($rows);
        self::assertGreaterThanOrEqual(2, (int) $rows->fetchColumn());
        $this->expectException(\PDOException::class);
        $this->database->exec("UPDATE community_profile_events SET event_code='TAMPERED' ORDER BY id DESC LIMIT 1");
    }

    public function testOwnProfileUpdateIsVersionedAndPublicConsentCanBeWithdrawn(): void
    {
        $this->selfLink($this->accountId, '1990-01-01');
        $profiles = new MySqlCommunityProfileRepository($this->connections);
        $created = $profiles->createPrivate($this->accountId, 'First Alias', $this->now);
        $mine = $profiles->mine($this->accountId);
        self::assertNotNull($mine);
        self::assertSame('First Alias', $mine['alias']);
        $profileId = UuidV7::fromString($created['public_id']);
        $updated = $profiles->updateOwn($this->accountId, $profileId, 1, 'Second Alias', 'PUBLIC', $this->now);
        self::assertSame(2, $updated['version']);
        $mine = $profiles->mine($this->accountId);
        self::assertNotNull($mine);
        self::assertSame('Second Alias', $mine['alias']);
        $reader = new MySqlCommunityFeedCandidates($this->connections);
        self::assertSame('Second Alias', $reader->publicProfileAlias($profileId, null));
        $private = $profiles->updateOwn($this->accountId, $profileId, 2, 'Second Alias', 'PRIVATE', $this->now);
        self::assertSame(3, $private['version']);
        self::assertNull($reader->publicProfileAlias($profileId, null));
        $events = $this->database->query("SELECT event_code FROM community_profile_events ORDER BY id");
        self::assertNotFalse($events);
        self::assertSame(['CREATED', 'PROFILE_UPDATED', 'PROFILE_UPDATED'], $events->fetchAll(PDO::FETCH_COLUMN));
    }

    public function testMinorPublicProfileIsDeniedWithoutGovernedGuardianApproval(): void
    {
        $this->selfLink($this->accountId, '2012-01-01');
        $profiles = new MySqlCommunityProfileRepository($this->connections);
        $created = $profiles->createPrivate($this->accountId, 'Young Reciter', $this->now);
        $this->expectException(\DomainException::class);
        $profiles->changeVisibility(
            $this->accountId,
            UuidV7::fromString($created['public_id']),
            1,
            'PUBLIC',
            $this->now
        );
    }

    public function testOperationReceiptReplaysExactRequestAndRejectsConflict(): void
    {
        $receipts = new MySqlCommunityOperationReceipts($this->connections);
        $submission = UuidV7::generate();
        $result = UuidV7::generate();
        $hash = hash('sha256', 'synthetic request', true);
        self::assertNull($receipts->claim(
            $submission,
            $this->workspaceId,
            $this->accountId,
            'CREATE_DRAFT',
            $hash,
            $this->now
        ));
        $receipts->complete($submission, $result, 'DRAFT', 1, $this->now);
        self::assertSame(
            ['public_id' => $result->toString(), 'status' => 'DRAFT', 'version' => 1],
            $receipts->completedForActor(
                $submission,
                $this->accountId,
                'CREATE_DRAFT',
                $hash
            )
        );
        self::assertSame(
            ['public_id' => $result->toString(), 'status' => 'DRAFT', 'version' => 1],
            $receipts->claim(
                $submission,
                $this->workspaceId,
                $this->accountId,
                'CREATE_DRAFT',
                $hash,
                $this->now
            )
        );
        $this->expectException(\DomainException::class);
        $receipts->claim(
            $submission,
            $this->workspaceId,
            $this->accountId,
            'CREATE_DRAFT',
            hash('sha256', 'different request', true),
            $this->now
        );
    }

    public function testGlobalSocialReceiptReplaysExactResultAndRejectsConflict(): void
    {
        $receipts = new MySqlCommunityGlobalReceipts($this->connections);
        $submission = UuidV7::generate();
        $result = UuidV7::generate();
        $fingerprint = hash('sha256', 'follow', true);
        self::assertNull($receipts->claim($submission, $this->accountId, 'FOLLOW', $fingerprint, $this->now));
        $receipts->complete($submission, $result, 'PENDING', 1, $this->now);
        self::assertSame(
            ['public_id' => $result->toString(), 'status' => 'PENDING', 'version' => 1],
            $receipts->completedForActor($submission, $this->accountId, 'FOLLOW', $fingerprint)
        );
        self::assertSame(
            ['public_id' => $result->toString(), 'status' => 'PENDING', 'version' => 1],
            $receipts->claim($submission, $this->accountId, 'FOLLOW', $fingerprint, $this->now)
        );
        $this->expectException(\DomainException::class);
        $receipts->completedForActor($submission, $this->accountId, 'FOLLOW', hash('sha256', 'other', true));
    }

    public function testFollowRequestAcceptanceAndBlockDominanceAreVersioned(): void
    {
        $fixture = new AuthorizationMySqlFixture($this->database, dirname(__DIR__, 3));
        [$otherAccount] = $fixture->account();
        $this->selfLink($this->accountId, '1990-01-01');
        $this->selfLink($otherAccount, '1990-01-01');
        $profiles = new MySqlCommunityProfileRepository($this->connections);
        $ownerProfile = $profiles->createPrivate($this->accountId, 'Owner', $this->now);
        $followerProfile = $profiles->createPrivate($otherAccount, 'Follower', $this->now);
        $social = new MySqlCommunitySocialRepository($this->connections);
        $ownerId = UuidV7::fromString($ownerProfile['public_id']);
        $followerId = UuidV7::fromString($followerProfile['public_id']);
        $request = $social->transition($otherAccount, $ownerId, 'FOLLOW', 0, $this->now);
        self::assertSame('PENDING', $request['status']);
        $incoming = $social->incoming($this->accountId);
        self::assertIsArray($incoming[0] ?? null);
        self::assertSame($followerId->toString(), $incoming[0]['profile_id']);
        self::assertSame('PENDING', $social->state($otherAccount, $ownerId)['follow_status']);
        self::assertSame(1, $social->state($otherAccount, $ownerId)['follow_version']);
        $accepted = $social->transition($this->accountId, $followerId, 'ACCEPT', 1, $this->now);
        self::assertSame('ACTIVE', $accepted['status']);
        self::assertSame($request['public_id'], $accepted['public_id']);
        $blocked = $social->transition($this->accountId, $followerId, 'BLOCK', 0, $this->now);
        self::assertSame('BLOCK', $blocked['status']);
        self::assertTrue($social->state($this->accountId, $followerId)['block_active']);
        self::assertSame($followerId->toString(), $social->safetyList($this->accountId)[0]['profile_id']);
        $relationship = $this->database->query("SELECT status,version FROM community_follows ORDER BY id DESC LIMIT 1");
        self::assertNotFalse($relationship);
        self::assertSame(['status' => 'REVOKED', 'version' => 3], $relationship->fetch(PDO::FETCH_ASSOC));
        try {
            $social->transition($otherAccount, $ownerId, 'FOLLOW', 3, $this->now);
            self::fail('Block must dominate a new follow attempt.');
        } catch (\DomainException) {
            self::addToAssertionCount(1);
        }
        $unblocked = $social->transition($this->accountId, $followerId, 'UNBLOCK', 1, $this->now);
        self::assertSame(2, $unblocked['version']);
        self::assertSame([], $social->safetyList($this->accountId));
        $muted = $social->transition($this->accountId, $followerId, 'MUTE', 0, $this->now);
        self::assertSame('MUTE', $muted['status']);
        self::assertTrue($social->state($this->accountId, $followerId)['mute_active']);
        self::assertTrue($social->safetyList($this->accountId)[0]['muted']);
        $social->transition($this->accountId, $followerId, 'UNMUTE', 1, $this->now);
        self::assertSame([], $social->safetyList($this->accountId));
        $current = $this->database->query('SELECT status FROM community_follows ORDER BY id DESC LIMIT 1');
        self::assertNotFalse($current);
        self::assertSame('REVOKED', $current->fetchColumn(), 'Unblock never silently restores a follow.');
        $renewed = $social->transition($otherAccount, $ownerId, 'FOLLOW', 3, $this->now);
        self::assertSame('PENDING', $renewed['status']);
        self::assertSame(4, $renewed['version']);
        $this->expectException(\DomainException::class);
        $social->transition($otherAccount, $ownerId, 'FOLLOW', 3, $this->now);
    }

    public function testMinorProfileCannotBeFollowedButCanBeBlockedForSafety(): void
    {
        $fixture = new AuthorizationMySqlFixture($this->database, dirname(__DIR__, 3));
        [$minorAccount] = $fixture->account();
        $this->selfLink($this->accountId, '1990-01-01');
        $this->selfLink($minorAccount, '2012-01-01');
        $profiles = new MySqlCommunityProfileRepository($this->connections);
        $minor = $profiles->createPrivate($minorAccount, 'Private Minor', $this->now);
        $minorId = UuidV7::fromString($minor['public_id']);
        $social = new MySqlCommunitySocialRepository($this->connections);
        try {
            $social->transition($this->accountId, $minorId, 'FOLLOW', 0, $this->now);
            self::fail('A minor profile must not receive social follow requests.');
        } catch (\DomainException) {
            self::addToAssertionCount(1);
        }
        $blocked = $social->transition($this->accountId, $minorId, 'BLOCK', 0, $this->now);
        self::assertSame('BLOCK', $blocked['status']);
    }

    public function testClipCreationRejectsUnapprovedOrForeignMedia(): void
    {
        $clips = new MySqlRecitationClipRepository($this->connections);
        $this->expectException(\DomainException::class);
        $clips->createDraft(
            $this->workspaceId,
            $this->accountId,
            UuidV7::generate(),
            UuidV7::generate(),
            UuidV7::generate(),
            UuidV7::generate(),
            UuidV7::generate(),
            '',
            'en',
            $this->now
        );
    }

    public function testPublicDiscoveryRendersSafelyAndRejectsMalformedCursor(): void
    {
        $runtime = ApplicationFactory::fromCurrentProcess()->createHttpRuntime();
        $response = $runtime->handle(HttpTestFactory::request('GET', '/community'));
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Community recitations', (string) $response->getBody());
        self::assertSame('private, no-store', $response->getHeaderLine('Cache-Control'));
        $invalid = $runtime->handle(HttpTestFactory::request('GET', '/community?cursor=../../private'));
        self::assertSame(422, $invalid->getStatusCode());
        self::assertStringNotContainsString('storage_key', (string) $invalid->getBody());
        $profileId = UuidV7::generate()->toString();
        $profile = $runtime->handle(HttpTestFactory::request('GET', '/community/profiles/' . $profileId));
        self::assertSame(404, $profile->getStatusCode());
        $profileClips = $runtime->handle(HttpTestFactory::request('GET', '/community/profiles/' . $profileId . '/clips'));
        self::assertSame(404, $profileClips->getStatusCode());
        $malformedProfile = $runtime->handle(HttpTestFactory::request('GET', '/community/profiles/invalid'));
        self::assertSame(404, $malformedProfile->getStatusCode());
    }

    public function testCommunityProfileRouteRequiresAuthentication(): void
    {
        $runtime = ApplicationFactory::fromCurrentProcess()->createHttpRuntime();
        $response = $runtime->handle(HttpTestFactory::request('GET', '/account/community/profile'));
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/login', $response->getHeaderLine('Location'));
        $post = $runtime->handle(HttpTestFactory::request('POST', '/account/community/profile/update'));
        self::assertSame(303, $post->getStatusCode());
        self::assertSame('/login', $post->getHeaderLine('Location'));
        $safety = $runtime->handle(HttpTestFactory::request('GET', '/account/community/safety'));
        self::assertSame(303, $safety->getStatusCode());
        self::assertSame('/login', $safety->getHeaderLine('Location'));
        $followers = $runtime->handle(HttpTestFactory::request('GET', '/account/community/followers'));
        self::assertSame(303, $followers->getStatusCode());
        self::assertSame('/login', $followers->getHeaderLine('Location'));
        $following = $runtime->handle(HttpTestFactory::request('GET', '/account/community/following'));
        self::assertSame(303, $following->getStatusCode());
        self::assertSame('/login', $following->getHeaderLine('Location'));
        $bookmarks = $runtime->handle(HttpTestFactory::request('GET', '/account/community/bookmarks'));
        self::assertSame(303, $bookmarks->getStatusCode());
        self::assertSame('/login', $bookmarks->getHeaderLine('Location'));
        $social = $runtime->handle(HttpTestFactory::request(
            'POST',
            '/account/community/social/' . UuidV7::generate()->toString() . '/follow'
        ));
        self::assertSame(303, $social->getStatusCode());
        self::assertSame('/login', $social->getHeaderLine('Location'));
    }

    public function testClipEngagementRoutesRejectAnonymousMutationAndUnknownClip(): void
    {
        $runtime = ApplicationFactory::fromCurrentProcess()->createHttpRuntime();
        $unknown = UuidV7::generate()->toString();
        $comments = $runtime->handle(HttpTestFactory::request('GET', '/clips/' . $unknown . '/comments'));
        self::assertSame(404, $comments->getStatusCode());
        foreach (
            ['/comments', '/comments/' . UuidV7::generate()->toString() . '/reply',
            '/comments/' . UuidV7::generate()->toString() . '/edit',
            '/comments/' . UuidV7::generate()->toString() . '/remove',
            '/interactions/reaction', '/interactions/bookmark'] as $suffix
        ) {
            $response = $runtime->handle(HttpTestFactory::request('POST', '/clips/' . $unknown . $suffix));
            self::assertSame(303, $response->getStatusCode());
            self::assertSame('/login', $response->getHeaderLine('Location'));
        }
    }

    public function testCreatorClipRoutesRequireAuthentication(): void
    {
        $runtime = ApplicationFactory::fromCurrentProcess()->createHttpRuntime();
        foreach (['GET', 'POST'] as $method) {
            $response = $runtime->handle(HttpTestFactory::request($method, '/workspace/community/clips'));
            self::assertSame(303, $response->getStatusCode());
            self::assertSame('/login', $response->getHeaderLine('Location'));
        }
        foreach (['update', 'submit', 'hide', 'remove', 'archive'] as $action) {
            $response = $runtime->handle(HttpTestFactory::request(
                'POST',
                '/workspace/community/clips/' . UuidV7::generate()->toString() . '/' . $action
            ));
            self::assertSame(303, $response->getStatusCode());
            self::assertSame('/login', $response->getHeaderLine('Location'));
        }
        $review = $runtime->handle(HttpTestFactory::request('GET', '/workspace/community/review'));
        self::assertSame(303, $review->getStatusCode());
        self::assertSame('/login', $review->getHeaderLine('Location'));
        $publish = $runtime->handle(HttpTestFactory::request(
            'POST',
            '/workspace/community/review/' . UuidV7::generate()->toString() . '/publish'
        ));
        self::assertSame(303, $publish->getStatusCode());
        self::assertSame('/login', $publish->getHeaderLine('Location'));
        $moderation = $runtime->handle(HttpTestFactory::request('GET', '/workspace/community/moderation'));
        self::assertSame(303, $moderation->getStatusCode());
        self::assertSame('/login', $moderation->getHeaderLine('Location'));
        $details = $runtime->handle(HttpTestFactory::request(
            'GET',
            '/workspace/community/moderation/' . UuidV7::generate()->toString()
        ));
        self::assertSame(303, $details->getStatusCode());
        self::assertSame('/login', $details->getHeaderLine('Location'));
        foreach (['assign', 'start', 'decide'] as $action) {
            $caseAction = $runtime->handle(HttpTestFactory::request(
                'POST',
                '/workspace/community/moderation/' . UuidV7::generate()->toString() . '/' . $action
            ));
            self::assertSame(303, $caseAction->getStatusCode());
            self::assertSame('/login', $caseAction->getHeaderLine('Location'));
        }
    }

    public function testPublicProfileReadRequiresCurrentConsentAndAdultSelfLink(): void
    {
        $personId = $this->selfLink($this->accountId, '1990-01-01');
        $profiles = new MySqlCommunityProfileRepository($this->connections);
        $created = $profiles->createPrivate($this->accountId, 'Public Reciter', $this->now);
        $profileId = UuidV7::fromString($created['public_id']);
        $reader = new MySqlCommunityFeedCandidates($this->connections);
        self::assertNull($reader->publicProfileAlias($profileId, null));
        $profiles->changeVisibility($this->accountId, $profileId, 1, 'PUBLIC', $this->now);
        self::assertSame('Public Reciter', $reader->publicProfileAlias($profileId, null));
        $fixture = new AuthorizationMySqlFixture($this->database, dirname(__DIR__, 3));
        [$viewerId] = $fixture->account();
        $this->database->prepare('INSERT INTO community_blocks (blocker_account_id,blocked_account_id,created_at) VALUES (:viewer,:creator,:created)')
            ->execute(['viewer' => $viewerId, 'creator' => $this->accountId,
                'created' => $this->now->format('Y-m-d H:i:s.u')]);
        self::assertNull($reader->publicProfileAlias($profileId, $viewerId));
        self::assertSame('Public Reciter', $reader->publicProfileAlias($profileId, null));
        $this->database->prepare('UPDATE people_persons SET birth_date=:birth WHERE id=:person')
            ->execute(['birth' => '2015-01-01', 'person' => $personId]);
        self::assertNull($reader->publicProfileAlias($profileId, null));
    }

    public function testPublicFeedKeysetQueryUsesDedicatedIndex(): void
    {
        $plan = $this->database->query(<<<'SQL'
EXPLAIN SELECT c.id
FROM recitation_clips c FORCE INDEX (ix_p10_clip_public_feed)
JOIN recitation_clip_public_snapshots s
  ON s.workspace_id=c.workspace_id AND s.clip_id=c.id AND s.clip_version=c.version
WHERE c.status='PUBLISHED' AND c.audience='PUBLIC'
ORDER BY c.published_at DESC,c.id DESC LIMIT 41
SQL);
        self::assertNotFalse($plan);
        $rows = $plan->fetchAll(PDO::FETCH_ASSOC);
        self::assertNotSame([], $rows);
        self::assertContains('ix_p10_clip_public_feed', array_column($rows, 'key'));
    }

    public function testClipPublicationRequiresCurrentP9ConsentAndCreatesSafeSnapshot(): void
    {
        $personId = $this->selfLink($this->accountId, '1990-01-01');
        $profiles = new MySqlCommunityProfileRepository($this->connections);
        $profile = $profiles->createPrivate($this->accountId, 'Test Reciter', $this->now);
        $profiles->changeVisibility(
            $this->accountId,
            UuidV7::fromString($profile['public_id']),
            1,
            'PUBLIC',
            $this->now
        );
        $fixture = new AuthorizationMySqlFixture($this->database, dirname(__DIR__, 3));
        [$reviewer] = $fixture->account();
        $assetPublic = UuidV7::generate();
        $variantPublic = UuidV7::generate();
        $time = $this->now->format('Y-m-d H:i:s.u');
        $this->database->prepare("INSERT INTO media_assets (public_id,workspace_id,owner_person_id,created_by_account_id,purpose_code,media_kind,status,original_storage_provider_code,original_storage_object_key,original_filename_safe,byte_size,version,created_at,updated_at) VALUES (:public,:workspace,:person,:actor,'RECITATION_EVIDENCE','AUDIO','APPROVED','LOCAL_PRIVATE',:storage,'synthetic.wav',4,1,:created,:updated)")->execute([
            'public' => $assetPublic->toBinary(), 'workspace' => $this->workspaceId,
            'person' => $personId, 'actor' => $this->accountId,
            'storage' => 'quarantine/' . $assetPublic->toString() . '.bin',
            'created' => $time, 'updated' => $time,
        ]);
        $assetId = (int) $this->database->lastInsertId();
        $this->database->prepare("INSERT INTO media_variants (public_id,workspace_id,asset_id,variant_code,storage_provider_code,storage_object_key,mime_type,byte_size,sha256,status,is_public_safe,created_at) VALUES (:public,:workspace,:asset,'NORMALIZED_V1','LOCAL_PRIVATE',:storage,'audio/mpeg',4,:sha,'READY',1,:created)")->execute([
            'public' => $variantPublic->toBinary(), 'workspace' => $this->workspaceId,
            'asset' => $assetId, 'storage' => 'variants/' . $variantPublic->toString() . '.mp3',
            'sha' => hash('sha256', 'test', true), 'created' => $time,
        ]);
        $this->database->prepare("INSERT INTO media_scan_results (workspace_id,asset_id,result_code,engine_code,safe_detail_code,scanned_at,created_at) VALUES (:workspace,:asset,'CLEAN','TEST_ONLY','CLEAN',:scanned,:created)")->execute([
            'workspace' => $this->workspaceId, 'asset' => $assetId, 'scanned' => $time, 'created' => $time,
        ]);
        $this->database->prepare("INSERT INTO media_delivery_policies (workspace_id,asset_id,visibility_code,rights_granted,consent_granted,version,created_at,updated_at) VALUES (:workspace,:asset,'PUBLIC',1,1,1,:created,:updated)")->execute([
            'workspace' => $this->workspaceId, 'asset' => $assetId, 'created' => $time, 'updated' => $time,
        ]);
        $this->database->prepare('INSERT INTO media_consent_reviews (workspace_id,asset_id,asset_version,reviewer_account_id,evidence_reference,evidence_sha256,participant_count,consent_count,minor_count,guardian_consent_count,created_at) VALUES (:workspace,:asset,1,:reviewer,:reference,:sha,1,1,0,0,:created)')->execute([
            'workspace' => $this->workspaceId, 'asset' => $assetId, 'reviewer' => $reviewer,
            'reference' => UuidV7::generate()->toBinary(), 'sha' => hash('sha256', 'synthetic-consent', true),
            'created' => $time,
        ]);
        $row = $this->referenceForClipFixture();
        $clips = new MySqlRecitationClipRepository($this->connections);
        $options = $clips->availablePassages();
        self::assertCount(1, $options);
        self::assertSame(1, $options[0]['surah_number']);
        $passage = $clips->resolvePassage(UuidV7::fromBinary($row['release_public']), 1, 1, 1);
        self::assertSame($row['ayah_public'], $passage['start']->toBinary());
        self::assertSame($row['ayah_public'], $passage['end']->toBinary());
        self::assertSame(
            [['asset_id' => $assetPublic->toString(),
            'variant_id' => $variantPublic->toString(), 'media_kind' => 'AUDIO']],
            $clips->eligibleMedia($this->workspaceId, $this->accountId)
        );
        self::assertSame([], $clips->eligibleMedia($this->workspaceId, $reviewer));
        $created = $clips->createDraft(
            $this->workspaceId,
            $this->accountId,
            $assetPublic,
            $variantPublic,
            UuidV7::fromBinary($row['release_public']),
            UuidV7::fromBinary($row['ayah_public']),
            UuidV7::fromBinary($row['ayah_public']),
            'Test recitation',
            'en',
            $this->now
        );
        self::assertSame(ClipStatus::DRAFT, $created->status);
        self::assertSame($created->publicId->toString(), $clips->listOwn($this->workspaceId, $this->accountId)[0]['public_id']);
        self::assertSame([], $clips->listOwn($this->workspaceId, $reviewer));
        $edited = $clips->updateDraft(
            $created,
            $this->accountId,
            'Test recitation',
            'en',
            $this->now,
            'ENABLED'
        );
        self::assertSame(2, $edited->version);
        $submitted = $clips->transition(
            $edited,
            ClipStatus::REVIEW_PENDING,
            $this->accountId,
            'CREATOR_SUBMIT',
            $this->now
        );
        self::assertSame([], $clips->reviewQueue($this->workspaceId, $this->accountId));
        self::assertSame($created->publicId->toString(), $clips->reviewQueue($this->workspaceId, $reviewer)[0]['public_id']);
        $clips->publicationEvidence($submitted)->assertPubliclyEligible();
        $published = $clips->transition(
            $submitted,
            ClipStatus::PUBLISHED,
            $reviewer,
            'INDEPENDENT_REVIEW',
            $this->now
        );
        self::assertSame(ClipStatus::PUBLISHED, $published->status);
        $snapshot = $this->database->query('SELECT COUNT(*) FROM recitation_clip_public_snapshots WHERE caption=\'Test recitation\'');
        self::assertNotFalse($snapshot);
        self::assertSame(1, (int) $snapshot->fetchColumn());
        $publicReader = new MySqlCommunityPublicClipReader($this->connections, $clips);
        $public = $publicReader->find($created->publicId, null);
        self::assertNotNull($public);
        self::assertSame('Test Reciter', $public['alias']);
        self::assertSame('Test recitation', $public['caption']);
        self::assertArrayNotHasKey('storage_key', $public);
        $feed = new MySqlCommunityFeedCandidates($this->connections);
        $candidates = $feed->newest(null, null, null, 'en', 1, false, null);
        self::assertNotSame([], $candidates);
        self::assertSame($created->publicId->toString(), $candidates[0]['clip_id']->toString());
        self::assertCount(1, $feed->newest(null, null, null, null, null, false, UuidV7::fromString($profile['public_id'])));
        self::assertSame([], $feed->newest(null, null, null, null, null, false, UuidV7::generate()));
        [$interactor] = $fixture->account();
        $interactorPersonId = $this->selfLink($interactor, '1990-01-01');
        self::assertSame([], $feed->newest($interactor, null, null, null, null, true, null));
        $this->database->prepare('INSERT INTO community_mutes (muter_account_id,muted_account_id,created_at) VALUES (:viewer,:creator,:created)')->execute([
            'viewer' => $interactor, 'creator' => $this->accountId, 'created' => $time,
        ]);
        self::assertSame([], $feed->newest($interactor, null, null, null, null, false, null));
        $this->database->prepare('UPDATE community_mutes SET revoked_at=:revoked WHERE muter_account_id=:viewer AND muted_account_id=:creator')->execute([
            'revoked' => $time, 'viewer' => $interactor, 'creator' => $this->accountId,
        ]);
        $interactions = new MySqlCommunityInteractionRepository($this->connections);
        $reaction = $interactions->transition(
            $this->workspaceId,
            $created->publicId,
            $interactor,
            'REACTION',
            'ADD',
            0,
            $this->now
        );
        self::assertSame('ADDED', $reaction['status']);
        $bookmark = $interactions->transition(
            $this->workspaceId,
            $created->publicId,
            $interactor,
            'BOOKMARK',
            'ADD',
            0,
            $this->now
        );
        self::assertSame('ADDED', $bookmark['status']);
        self::assertSame([$created->publicId->toString()], array_map(
            static fn (UuidV7 $id): string => $id->toString(),
            $interactions->bookmarkedClipIds($interactor)
        ));
        self::assertSame(
            ['reaction' => ['active' => true, 'version' => 1],
            'bookmark' => ['active' => true, 'version' => 1]],
            $interactions->state($this->workspaceId, $created->publicId, $interactor)
        );
        $reactionRemoved = $interactions->transition(
            $this->workspaceId,
            $created->publicId,
            $interactor,
            'REACTION',
            'REMOVE',
            1,
            $this->now
        );
        self::assertSame(2, $reactionRemoved['version']);
        self::assertSame(
            ['active' => false, 'version' => 2],
            $interactions->state($this->workspaceId, $created->publicId, $interactor)['reaction']
        );
        $activeReactions = $this->database->query('SELECT COUNT(*) FROM community_reactions WHERE revoked_at IS NULL');
        self::assertNotFalse($activeReactions);
        self::assertSame(0, (int) $activeReactions->fetchColumn());
        $comments = new MySqlCommunityCommentRepository($this->connections);
        $root = $comments->create(
            $this->workspaceId,
            $created->publicId,
            $interactor,
            null,
            'Clear recitation.',
            $this->now
        );
        self::assertSame('VISIBLE', $root['status']);
        $reply = $comments->create(
            $this->workspaceId,
            $created->publicId,
            $this->accountId,
            UuidV7::fromString($root['public_id']),
            'Thank you.',
            $this->now
        );
        self::assertSame('VISIBLE', $reply['status']);
        $visibleComments = $comments->visible($this->workspaceId, $created->publicId, $interactor);
        self::assertCount(2, $visibleComments);
        self::assertSame('Clear recitation.', $visibleComments[0]['body']);
        self::assertTrue($visibleComments[0]['is_mine']);
        self::assertSame($root['public_id'], $visibleComments[1]['parent_id']);
        self::assertFalse($visibleComments[1]['is_mine']);
        try {
            $comments->create(
                $this->workspaceId,
                $created->publicId,
                $interactor,
                UuidV7::fromString($reply['public_id']),
                'Nested too far.',
                $this->now
            );
            self::fail('Replies cannot nest beyond one level.');
        } catch (\DomainException) {
            self::addToAssertionCount(1);
        }
        $editedComment = $comments->transition(
            $this->workspaceId,
            $created->publicId,
            UuidV7::fromString($root['public_id']),
            $interactor,
            'EDIT',
            1,
            'A clear recitation.',
            $this->now
        );
        self::assertSame('EDITED', $editedComment['status']);
        $removedComment = $comments->transition(
            $this->workspaceId,
            $created->publicId,
            UuidV7::fromString($root['public_id']),
            $interactor,
            'REMOVE',
            2,
            '',
            $this->now
        );
        self::assertSame('REMOVED', $removedComment['status']);
        self::assertCount(1, $comments->visible($this->workspaceId, $created->publicId, null));
        $this->database->prepare("UPDATE recitation_clips SET comment_policy='REVIEW' WHERE public_id=:clip")
            ->execute(['clip' => $created->publicId->toBinary()]);
        $held = $comments->create(
            $this->workspaceId,
            $created->publicId,
            $interactor,
            null,
            'Awaiting review.',
            $this->now
        );
        self::assertSame('MODERATION_HELD', $held['status']);
        self::assertCount(1, $comments->visible($this->workspaceId, $created->publicId, null));
        self::assertCount(2, $comments->visible($this->workspaceId, $created->publicId, $interactor));
        self::assertSame([], $comments->heldQueue($this->workspaceId, $this->accountId));
        self::assertSame([], $comments->heldQueue($this->workspaceId, $interactor));
        $heldQueue = $comments->heldQueue($this->workspaceId, $reviewer);
        self::assertSame($held['public_id'], $heldQueue[0]['public_id']);
        $moderatedComment = $comments->moderateHeld(
            $this->workspaceId,
            UuidV7::fromString($held['public_id']),
            $reviewer,
            'REMOVE',
            1,
            $this->now
        );
        self::assertSame('REMOVED', $moderatedComment['status']);
        self::assertCount(1, $comments->visible($this->workspaceId, $created->publicId, $interactor));
        $this->database->prepare('UPDATE people_persons SET birth_date=:birth WHERE id=:person')
            ->execute(['birth' => '2012-01-01', 'person' => $interactorPersonId]);
        self::assertCount(1, $comments->visible($this->workspaceId, $created->publicId, $interactor));
        $this->database->prepare('UPDATE people_persons SET birth_date=:birth WHERE id=:person')
            ->execute(['birth' => '1990-01-01', 'person' => $interactorPersonId]);
        $this->database->prepare('INSERT INTO community_blocks (blocker_account_id,blocked_account_id,created_at) VALUES (:owner,:interactor,:created)')->execute([
            'owner' => $this->accountId, 'interactor' => $interactor, 'created' => $time,
        ]);
        self::assertCount(0, $comments->visible($this->workspaceId, $created->publicId, $interactor));
        self::assertNull($publicReader->find($created->publicId, $interactor));
        $this->database->prepare('UPDATE community_blocks SET revoked_at=:revoked WHERE blocker_account_id=:owner AND blocked_account_id=:interactor')->execute([
            'revoked' => $time, 'owner' => $this->accountId, 'interactor' => $interactor,
        ]);
        $this->database->prepare('UPDATE media_delivery_policies SET consent_granted=0 WHERE asset_id=:asset')->execute(['asset' => $assetId]);
        self::assertNull($publicReader->find($created->publicId, null));
        try {
            $clips->publicationEvidence($published)->assertPubliclyEligible();
            self::fail('Withdrawn P9 consent must immediately block public eligibility.');
        } catch (\DomainException) {
            self::addToAssertionCount(1);
        }
        $this->database->prepare('UPDATE media_delivery_policies SET consent_granted=1 WHERE asset_id=:asset')->execute(['asset' => $assetId]);
        $birthDate = $this->database->prepare('UPDATE people_persons SET birth_date=:birth WHERE id=:person');
        $birthDate->execute(['birth' => '2012-01-01', 'person' => $personId]);
        self::assertNull($publicReader->find($created->publicId, null), 'An age correction must hide a public Clip.');
        $birthDate->execute(['birth' => '1990-01-01', 'person' => $personId]);
        self::assertNotNull($publicReader->find($created->publicId, null));
        $accountStatus = $this->database->prepare('UPDATE user_accounts SET account_status=:status WHERE id=:account');
        $accountStatus->execute(['status' => 'SUSPENDED', 'account' => $this->accountId]);
        self::assertNull($publicReader->find($created->publicId, null), 'Suspending the creator must hide a public Clip.');
        $accountStatus->execute(['status' => 'ACTIVE', 'account' => $this->accountId]);
        self::assertNotNull($publicReader->find($created->publicId, null));
        $source = $clips->createDraft(
            $this->workspaceId,
            $this->accountId,
            $assetPublic,
            $variantPublic,
            UuidV7::fromBinary($row['release_public']),
            UuidV7::fromBinary($row['ayah_public']),
            UuidV7::fromBinary($row['ayah_public']),
            'Original replacement source',
            'en',
            $this->now
        );
        $sourcePending = $clips->transition(
            $source,
            ClipStatus::REVIEW_PENDING,
            $this->accountId,
            'CREATOR_SUBMIT',
            $this->now
        );
        $clips->publicationEvidence($sourcePending)->assertPubliclyEligible();
        $clips->transition(
            $sourcePending,
            ClipStatus::PUBLISHED,
            $reviewer,
            'INDEPENDENT_REVIEW',
            $this->now
        );
        $replacement = $clips->createDraft(
            $this->workspaceId,
            $this->accountId,
            $assetPublic,
            $variantPublic,
            UuidV7::fromBinary($row['release_public']),
            UuidV7::fromBinary($row['ayah_public']),
            UuidV7::fromBinary($row['ayah_public']),
            'Replacement recitation',
            'en',
            $this->now,
            $source->publicId
        );
        try {
            $clips->createDraft(
                $this->workspaceId,
                $this->accountId,
                $assetPublic,
                $variantPublic,
                UuidV7::fromBinary($row['release_public']),
                UuidV7::fromBinary($row['ayah_public']),
                UuidV7::fromBinary($row['ayah_public']),
                'Duplicate replacement',
                'en',
                $this->now,
                $source->publicId
            );
            self::fail('Only one active replacement may target a published Clip.');
        } catch (\DomainException) {
            self::addToAssertionCount(1);
        }
        $replacementPending = $clips->transition(
            $replacement,
            ClipStatus::REVIEW_PENDING,
            $this->accountId,
            'CREATOR_SUBMIT',
            $this->now
        );
        $clips->publicationEvidence($replacementPending)->assertPubliclyEligible();
        $clips->transition(
            $replacementPending,
            ClipStatus::PUBLISHED,
            $reviewer,
            'INDEPENDENT_REVIEW',
            $this->now
        );
        self::assertSame(ClipStatus::SUPERSEDED, $clips->lock($this->workspaceId, $source->publicId)?->status);
        self::assertNull($publicReader->find($source->publicId, null));
        self::assertNotNull($publicReader->find($replacement->publicId, null));
        try {
            $this->database->prepare(
                'UPDATE recitation_clips SET supersedes_clip_id=:source WHERE public_id=:other'
            )->execute(['source' => $source->internalId, 'other' => $created->publicId->toBinary()]);
            self::fail('The database must reject a second active successor.');
        } catch (\PDOException) {
            self::addToAssertionCount(1);
        }
        [$reporter] = $fixture->account();
        $reports = new MySqlCommunityReportRepository($this->connections, $clips);
        $target = $reports->visibleTarget($created->publicId, $reporter);
        $cipher = new SodiumContactCipher(str_repeat('c', 32), 'test-v1');
        $report = $reports->submit(
            $target,
            $reporter,
            'PRIVACY',
            $cipher->encrypt('Synthetic safety concern'),
            $cipher->keyId(),
            $this->now
        );
        self::assertSame('SUBMITTED', $report['status']);
        try {
            $reports->submit(
                $target,
                $reporter,
                'PRIVACY',
                $cipher->encrypt('Repeated synthetic safety concern'),
                $cipher->keyId(),
                $this->now
            );
            self::fail('A reporter must not create a duplicate report for one Clip.');
        } catch (\DomainException) {
            self::addToAssertionCount(1);
        }
        $caseQuery = $this->database->prepare('SELECT public_id FROM community_moderation_cases WHERE workspace_id=:workspace AND clip_id=:clip');
        $caseQuery->execute(['workspace' => $this->workspaceId, 'clip' => $created->internalId]);
        $caseBinary = $caseQuery->fetchColumn();
        self::assertIsString($caseBinary);
        $cases = new MySqlCommunityModerationRepository($this->connections);
        $caseId = UuidV7::fromBinary($caseBinary);
        $queue = $cases->queue($this->workspaceId, $reviewer);
        self::assertSame($caseId->toString(), $queue[0]['public_id']);
        self::assertSame(1, $queue[0]['report_count']);
        self::assertArrayNotHasKey('reporter_account_id', $queue[0]);
        self::assertSame([], $cases->queue($this->workspaceId, $this->accountId));
        self::assertSame([], $cases->queue($this->workspaceId, $reporter));
        $privateReports = $cases->caseReports($this->workspaceId, $caseId, $reviewer);
        self::assertSame('PRIVACY', $privateReports[0]['reason']);
        self::assertSame('Synthetic safety concern', $cipher->decrypt($privateReports[0]['packed_ciphertext']));
        self::assertArrayNotHasKey('reporter_account_id', $privateReports[0]);
        self::assertSame([], $cases->caseReports($this->workspaceId, $caseId, $this->accountId));
        self::assertSame([], $cases->caseReports($this->workspaceId, $caseId, $reporter));
        $case = $cases->lock($this->workspaceId, $caseId);
        self::assertNotNull($case);
        try {
            $cases->assignSelf($case, $reporter, $this->now);
            self::fail('Reporter must not review their own report.');
        } catch (\DomainException) {
            self::addToAssertionCount(1);
        }
        $cases->assignSelf($case, $reviewer, $this->now);
        self::assertTrue($cases->queue($this->workspaceId, $reviewer)[0]['assigned_to_me']);
        $case = $cases->lock($this->workspaceId, $caseId);
        self::assertNotNull($case);
        $cases->startReview($case, $reviewer, $this->now);
        $case = $cases->lock($this->workspaceId, $caseId);
        self::assertNotNull($case);
        $hidden = $clips->transition(
            $published,
            ClipStatus::HIDDEN,
            $reviewer,
            'MODERATION_HIDE',
            $this->now
        );
        self::assertSame(ClipStatus::HIDDEN, $hidden->status);
        self::assertSame('ACTIONED', $cases->decide($case, $reviewer, 'HIDE', 'PRIVACY', $this->now));
        $appeals = new MySqlCommunityModerationAppealRepository($this->connections);
        $appealable = $appeals->eligibleDecision($this->workspaceId, $caseId, $this->accountId);
        self::assertNotNull($appealable);
        $appealId = $appeals->submit(
            $appealable,
            $this->accountId,
            $cipher->encrypt('The consent evidence remains current.'),
            $cipher->keyId(),
            $this->now
        );
        self::assertNull($appeals->eligibleDecision($this->workspaceId, $caseId, $this->accountId));
        self::assertSame([], $appeals->queue($this->workspaceId, $reviewer));
        self::assertSame([], $appeals->queue($this->workspaceId, $reporter));
        [$appealReviewer] = $fixture->account();
        $appealQueue = $appeals->queue($this->workspaceId, $appealReviewer);
        self::assertSame($appealId->toString(), $appealQueue[0]['public_id']);
        $appealStatement = $appeals->statement($this->workspaceId, $appealId, $appealReviewer);
        self::assertNotNull($appealStatement);
        self::assertSame(
            'The consent evidence remains current.',
            $cipher->decrypt($appealStatement['packed_ciphertext'])
        );
        $appeal = $appeals->lock($this->workspaceId, $appealId, $appealReviewer);
        self::assertNotNull($appeal);
        $appeals->decide($appeal, $appealReviewer, 'RESTORED', 'EVIDENCE_CONFIRMED', $this->now);
        self::assertSame([], $appeals->queue($this->workspaceId, $appealReviewer));
        try {
            $this->database->exec("UPDATE community_moderation_appeal_reviews SET reason_code='TAMPERED'");
            self::fail('Appeal review evidence must be immutable.');
        } catch (\PDOException) {
            self::addToAssertionCount(1);
        }
        $notifications = new MySqlCommunityNotificationIntentRepository($this->connections);
        $notifications->enqueue(
            $this->workspaceId,
            $this->accountId,
            'APPEAL_DECISION',
            $appealId,
            2,
            'RESTORED',
            $this->now
        );
        $notifications->enqueue(
            $this->workspaceId,
            $this->accountId,
            'APPEAL_DECISION',
            $appealId,
            2,
            'RESTORED',
            $this->now
        );
        $notificationCount = $this->database->query(
            "SELECT COUNT(*) FROM community_notification_intents WHERE type_code='APPEAL_DECISION'"
        );
        self::assertNotFalse($notificationCount);
        self::assertSame(1, (int) $notificationCount->fetchColumn());
        $leaseOwner = UuidV7::generate();
        $claimed = $notifications->claimDue(
            $leaseOwner,
            $this->now,
            $this->now->modify('+120 seconds'),
            25
        );
        self::assertSame('APPEAL_DECISION', $claimed[0]['type_code']);
        self::assertTrue($notifications->retry(
            $claimed[0]['id'],
            $leaseOwner,
            'PROVIDER_FAILURE',
            $this->now->modify('+30 seconds'),
            $this->now
        ));
        self::assertSame([], $notifications->claimDue(
            UuidV7::generate(),
            $this->now,
            $this->now->modify('+120 seconds'),
            25
        ));
        $secondOwner = UuidV7::generate();
        $claimed = $notifications->claimDue(
            $secondOwner,
            $this->now->modify('+31 seconds'),
            $this->now->modify('+151 seconds'),
            25
        );
        self::assertSame(2, $claimed[0]['attempt_count']);
        self::assertTrue($notifications->delivered(
            $claimed[0]['id'],
            $secondOwner,
            $this->now->modify('+31 seconds')
        ));
        try {
            $this->database->exec("DELETE FROM community_notification_events WHERE event_code='CREATED'");
            self::fail('Notification delivery history must be immutable.');
        } catch (\PDOException) {
            self::addToAssertionCount(1);
        }
        $clips->publicationEvidence($hidden)->assertPubliclyEligible();
        $restored = $clips->transition(
            $hidden,
            ClipStatus::PUBLISHED,
            $appealReviewer,
            'APPEAL_RESTORED',
            $this->now
        );
        self::assertNotNull($publicReader->find($created->publicId, null));
        [$safetyReporter] = $fixture->account();
        $safetyTarget = $reports->visibleTarget($created->publicId, $safetyReporter);
        $reports->submit(
            $safetyTarget,
            $safetyReporter,
            'CHILD_SAFETY',
            $cipher->encrypt('New synthetic child-safety concern'),
            $cipher->keyId(),
            $this->now
        );
        $reopened = $cases->lock($this->workspaceId, $caseId);
        self::assertNotNull($reopened);
        self::assertSame('SUBMITTED', $reopened['status']);
        self::assertNull($publicReader->find($created->publicId, null));
        $hiddenAgain = $clips->transition(
            $restored,
            ClipStatus::HIDDEN,
            $reviewer,
            'CHILD_SAFETY_HOLD',
            $this->now
        );
        $removed = $clips->transition(
            $hiddenAgain,
            ClipStatus::REMOVED,
            $this->accountId,
            'CREATOR_REMOVE',
            $this->now
        );
        self::assertSame(ClipStatus::REMOVED, $removed->status);
        $archived = $clips->transition(
            $removed,
            ClipStatus::ARCHIVED,
            $this->accountId,
            'CREATOR_ARCHIVE',
            $this->now
        );
        self::assertSame(ClipStatus::ARCHIVED, $archived->status);
        $this->expectException(\PDOException::class);
        $this->database->exec("UPDATE community_moderation_decisions SET reason_code='TAMPERED' ORDER BY id DESC LIMIT 1");
    }

    private function selfLink(int $accountId, string $birthDate): int
    {
        $time = $this->now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
        $this->database->prepare('INSERT INTO people_persons (public_id,registry_code,status,birth_date,created_by_account_id,version,created_at,updated_at) VALUES (:public,:registry,\'ACTIVE\',:birth,:account,1,:created,:updated)')->execute([
            'public' => UuidV7::generate()->toBinary(), 'registry' => 'P10-' . bin2hex(random_bytes(8)),
            'birth' => $birthDate, 'account' => $accountId, 'created' => $time, 'updated' => $time,
        ]);
        $personId = (int) $this->database->lastInsertId();
        $this->database->prepare("INSERT INTO people_account_links (public_id,account_id,person_id,link_type,status,version,linked_at,created_at,updated_at) VALUES (:public,:account,:person,'SELF','ACTIVE',1,:linked,:created,:updated)")->execute([
            'public' => UuidV7::generate()->toBinary(), 'account' => $accountId,
            'person' => $personId, 'linked' => $time, 'created' => $time, 'updated' => $time,
        ]);
        return $personId;
    }

    /** @return array{release_public: string, ayah_public: string} */
    private function referenceForClipFixture(): array
    {
        $reference = $this->database->query("SELECT r.public_id AS release_public,a.public_id AS ayah_public FROM quran_reference_releases r JOIN quran_ayahs a ON a.release_id=r.id WHERE r.status='ACTIVE' ORDER BY a.global_ayah_ordinal LIMIT 1");
        self::assertNotFalse($reference);
        $existing = $reference->fetch(PDO::FETCH_ASSOC);
        if (is_array($existing)) {
            self::assertIsString($existing['release_public']);
            self::assertIsString($existing['ayah_public']);
            return $existing;
        }

        // The full MySQL runner starts with an empty P4 content ledger. Keep
        // this P10 test transactional and independent of a manual baseline import.
        $time = $this->now->format('Y-m-d H:i:s.u');
        $artifacts = [];
        foreach (['CANONICAL_TEXT', 'STRUCTURAL_METADATA'] as $role) {
            $source = $this->database->prepare('SELECT id FROM quran_reference_sources WHERE content_role=:role AND status=\'APPROVED\' LIMIT 1');
            $source->execute(['role' => $role]);
            $sourceId = $source->fetchColumn();
            self::assertNotFalse($sourceId, 'P4 source catalog seed must be installed.');
            $artifact = $this->database->prepare("INSERT INTO quran_source_artifacts (public_id,source_id,artifact_code,artifact_role,repository_relative_path,original_filename,media_type,byte_size,sha256,acquisition_profile,acquired_at,status,version,created_at,updated_at) VALUES (:public,:source,:code,:role,'tests/fixtures/p10-synthetic-reference','p10-synthetic-reference.txt','text/plain',1,:sha,'TEST_ONLY',:acquired,'VERIFIED',1,:created,:updated)");
            $artifact->execute([
                'public' => UuidV7::generate()->toBinary(), 'source' => $sourceId,
                'code' => 'P10_TEST_' . $role . '_' . bin2hex(random_bytes(4)),
                'role' => $role, 'sha' => hash('sha256', 'P10-' . $role, true),
                'acquired' => $time, 'created' => $time, 'updated' => $time,
            ]);
            $artifacts[$role] = (int) $this->database->lastInsertId();
        }
        $releasePublic = UuidV7::generate();
        $release = $this->database->prepare("INSERT INTO quran_reference_releases (public_id,release_code,release_version,status,manifest_sha256,manifest_schema_version,validation_policy_version,created_by_account_id,approved_by_account_id,activated_by_account_id,version,created_at,approved_at,activated_at,updated_at) VALUES (:public,:code,:version,'ACTIVE',:hash,'p10.test.v1','p10.test.v1',:actor,:approver,:activator,1,:created,:approved,:activated,:updated)");
        $release->execute([
            'public' => $releasePublic->toBinary(),
            'code' => 'P10_TEST_' . bin2hex(random_bytes(4)),
            'version' => 'p10-test-' . bin2hex(random_bytes(4)),
            'hash' => hash('sha256', 'P10 synthetic reference', true),
            'actor' => $this->accountId, 'approver' => $this->accountId,
            'activator' => $this->accountId, 'created' => $time,
            'approved' => $time, 'activated' => $time, 'updated' => $time,
        ]);
        $releaseId = (int) $this->database->lastInsertId();
        $surah = $this->database->prepare("INSERT INTO quran_surahs (public_id,release_id,metadata_source_artifact_id,surah_number,ayah_count,first_global_ayah_ordinal,last_global_ayah_ordinal,arabic_name,metadata_sha256,created_at) VALUES (:public,:release,:artifact,1,1,1,1,'الفاتحة',:sha,:created)");
        $surah->execute([
            'public' => UuidV7::generate()->toBinary(), 'release' => $releaseId,
            'artifact' => $artifacts['STRUCTURAL_METADATA'],
            'sha' => hash('sha256', 'P10 synthetic surah', true), 'created' => $time,
        ]);
        $surahId = (int) $this->database->lastInsertId();
        $ayahPublic = UuidV7::generate();
        $text = 'بِسْمِ اللَّهِ';
        $ayah = $this->database->prepare('INSERT INTO quran_ayahs (public_id,release_id,surah_id,canonical_text_source_artifact_id,surah_number,ayah_number,global_ayah_ordinal,canonical_uthmani_text,text_byte_size,text_sha256,created_at) VALUES (:public,:release,:surah,:artifact,1,1,1,:text,:bytes,:sha,:created)');
        $ayah->execute([
            'public' => $ayahPublic->toBinary(), 'release' => $releaseId,
            'surah' => $surahId, 'artifact' => $artifacts['CANONICAL_TEXT'],
            'text' => $text, 'bytes' => strlen($text),
            'sha' => hash('sha256', $text, true), 'created' => $time,
        ]);
        return ['release_public' => $releasePublic->toBinary(), 'ayah_public' => $ayahPublic->toBinary()];
    }
}
