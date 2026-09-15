<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\TrustedArchive;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\RecordPassport\Application\RecordPassportProjectionPolicy;
use Qmdb\Modules\RecordPassport\Domain\RecordPassportShareCode;
use Qmdb\Modules\TrustedArchive\Application\LegacyRecordPayloadValidator;
use Qmdb\Modules\TrustedArchive\Domain\TrustedArchiveHasher;

final class TrustedArchiveAndPassportBoundaryTest extends TestCase
{
    public function testArchiveChainRejectsChangedHistoricalRecord(): void
    {
        $hasher = new TrustedArchiveHasher();
        $firstManifest = hash('sha256', 'first', true);
        $first = $hasher->recordHash(1, $firstManifest, null);
        $secondManifest = hash('sha256', 'second', true);
        $second = $hasher->recordHash(2, $secondManifest, $first);
        self::assertTrue($hasher->verifyChain([
            ['sequence' => 1, 'manifest_sha256' => $firstManifest, 'previous_record_sha256' => null, 'record_sha256' => $first],
            ['sequence' => 2, 'manifest_sha256' => $secondManifest, 'previous_record_sha256' => $first, 'record_sha256' => $second],
        ]));
        self::assertFalse($hasher->verifyChain([
            ['sequence' => 1, 'manifest_sha256' => $firstManifest, 'previous_record_sha256' => null, 'record_sha256' => $first],
            ['sequence' => 2, 'manifest_sha256' => hash('sha256', 'altered', true), 'previous_record_sha256' => $first, 'record_sha256' => $second],
        ]));
    }

    public function testPublicPassportProjectionRemovesPersonAndAuthorityData(): void
    {
        $projection = (new RecordPassportProjectionPolicy())->publicProjection([
            'source_kind' => 'CERTIFICATE', 'certificate_number' => 'QMDB-2026-W-000001', 'person_id' => 91,
            'account_id' => 77, 'email' => 'private@example.test', 'outcome_code' => 'WINNER',
        ]);
        self::assertSame('CERTIFICATE', $projection['source_kind']);
        self::assertArrayNotHasKey('person_id', $projection);
        self::assertArrayNotHasKey('account_id', $projection);
        self::assertArrayNotHasKey('email', $projection);
        self::assertSame(32, strlen(RecordPassportShareCode::generate()->hash()));
    }

    public function testLegacyPayloadIsClosedAndDoesNotAcceptUnknownFields(): void
    {
        $validator = new LegacyRecordPayloadValidator();
        self::assertSame('RECOGNITION', $validator->validateJson('{"record_type":"RECOGNITION","subject_reference":"person:public","occurred_on":"2020-01-01"}')['record_type']);
        $this->expectException(\InvalidArgumentException::class);
        $validator->validateJson('{"record_type":"RECOGNITION","subject_reference":"person:public","public":true}');
    }
}
