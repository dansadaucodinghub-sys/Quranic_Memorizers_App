<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\SecurityAudit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditCheckpointEnvelope;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditCheckpointPublicationReceipt;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditHashChain;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditCheckpointPublisher;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditAppendCommand;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditAppendResult;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditRecorder;
use Qmdb\Modules\SecurityAudit\Domain\CanonicalSecurityEventMetadataSerializer;
use Qmdb\Modules\SecurityAudit\Domain\SecurityAuditStreamIdentity;
use Qmdb\Modules\SecurityAudit\Domain\SecurityAuditStreamType;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCodeCatalog;
use Qmdb\Shared\Identifier\UuidV7;

final class SecurityAuditFoundationTest extends TestCase
{
    public function testStreamIdentityIsDeterministicAndScopeRulesAreEnforced(): void
    {
        $account = UuidV7::generate();
        $first = new SecurityAuditStreamIdentity(SecurityAuditStreamType::ACCOUNT, $account);
        $second = new SecurityAuditStreamIdentity(SecurityAuditStreamType::ACCOUNT, $account);

        self::assertSame($first->streamKey()->hex(), $second->streamKey()->hex());
        self::assertSame(32, strlen($first->streamKey()->binary()));
        self::assertSame(32, strlen(SecurityAuditStreamIdentity::platform()->key()));

        $this->expectException(\InvalidArgumentException::class);
        new SecurityAuditStreamIdentity(SecurityAuditStreamType::WORKSPACE, null);
    }

    public function testMetadataIsCanonicalBoundedAndRejectsUnsafeFacts(): void
    {
        $serializer = new CanonicalSecurityEventMetadataSerializer(256);

        self::assertSame(
            '{"assignment_public_id":"019bd95d-4a9b-7e11-9efb-41e7111f0001","role_code":"platform.security_administrator"}',
            $serializer->serialize([
                'role_code' => 'platform.security_administrator',
                'assignment_public_id' => '019bd95d-4a9b-7e11-9efb-41e7111f0001',
            ]),
        );

        $this->expectException(\InvalidArgumentException::class);
        $serializer->serialize(['password' => 'never-permitted']);
    }

    public function testEventAndCheckpointHashesAreDeterministicAndOrderIndependent(): void
    {
        $chain = new SecurityAuditHashChain();
        $key = str_repeat('k', 32);
        $metadataHash = hash('sha256', '{}', true);
        $fields = [
            'event_code' => SecurityEventCode::ACCOUNT_SUSPENDED->value,
            'sequence_number' => 1,
            'stream_type' => 'ACCOUNT',
        ];
        $first = $chain->eventHash($fields, $metadataHash, SecurityAuditHashChain::GENESIS_HASH, $key);
        $second = $chain->eventHash(array_reverse($fields, true), $metadataHash, SecurityAuditHashChain::GENESIS_HASH, $key);

        self::assertSame($first, $second);
        self::assertNotSame($first, $chain->eventHash($fields, $metadataHash, str_repeat('x', 32), $key));

        $heads = [
            ['stream_public_id' => 'b', 'stream_type' => 'ACCOUNT', 'scope_public_id' => 'b', 'last_sequence' => 1, 'last_event_hash' => bin2hex($first)],
            ['stream_public_id' => 'a', 'stream_type' => 'PLATFORM', 'scope_public_id' => null, 'last_sequence' => 0, 'last_event_hash' => null],
        ];
        self::assertSame($chain->headsDigest($heads), $chain->headsDigest(array_reverse($heads)));
        self::assertSame(32, strlen($chain->checkpointHash(
            UuidV7::generate()->toString(),
            1,
            2,
            1,
            $chain->headsDigest($heads),
            SecurityAuditHashChain::GENESIS_HASH,
            1,
            new DateTimeImmutable('2026-08-29T12:00:00.000000Z'),
            $key,
        )));
    }

    public function testCodeCatalogAndDeferredPublisherContractAreExplicit(): void
    {
        self::assertContains(SecurityEventCode::BREAK_GLASS_REVIEW_COMPLETED, SecurityEventCodeCatalog::all());
        self::assertSame(SecurityEventCode::PASSKEY_ADDED, SecurityEventCodeCatalog::fromTrustedString('identity.passkey.added'));

        $publisher = new class implements SecurityAuditCheckpointPublisher {
            public function publish(SecurityAuditCheckpointEnvelope $checkpoint): SecurityAuditCheckpointPublicationReceipt
            {
                return new SecurityAuditCheckpointPublicationReceipt('test-receipt', $checkpoint->createdAt);
            }
        };
        $now = new DateTimeImmutable('2026-08-29T12:00:00Z');
        $receipt = $publisher->publish(new SecurityAuditCheckpointEnvelope(
            UuidV7::generate()->toString(),
            1,
            str_repeat('a', 64),
            str_repeat('0', 64),
            str_repeat('b', 64),
            1,
            1,
            1,
            $now,
        ));

        self::assertSame('test-receipt', $receipt->providerReference);
    }

    public function testPrivilegedAccessUsesTheWorkspaceStreamWhenTheAccessScopeIsWorkspaceBound(): void
    {
        $recorder = new class implements SecurityAuditRecorder {
            public ?SecurityAuditAppendCommand $command = null;

            public function append(SecurityAuditAppendCommand $command): SecurityAuditAppendResult
            {
                $this->command = $command;

                return new SecurityAuditAppendResult(UuidV7::generate()->toString(), UuidV7::generate()->toString(), 1);
            }
        };
        $workspace = UuidV7::generate();
        $subject = UuidV7::generate();
        $appender = new SecurityAuditEventAppender($recorder);

        $appender->privilegedAccess(
            SecurityEventCode::TEMPORARY_ACTIVATED,
            true,
            $workspace->toString(),
            \Qmdb\Modules\SecurityAudit\Domain\SecurityEventSubjectKind::PRIVILEGED_ACCESS,
            $subject->toString(),
            $subject->toString(),
            new DateTimeImmutable('2026-08-29T12:00:00.000000Z'),
        );

        self::assertNotNull($recorder->command);
        self::assertSame(SecurityAuditStreamType::WORKSPACE, $recorder->command->stream->type);
        self::assertSame($workspace->toString(), $recorder->command->workspacePublicId?->toString());
    }
}
