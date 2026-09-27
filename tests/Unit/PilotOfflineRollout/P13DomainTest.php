<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\PilotOfflineRollout;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Modules\PilotOfflineRollout\Domain\CanonicalJson;
use Qmdb\Modules\PilotOfflineRollout\Domain\DeviceRequestSignature;
use Qmdb\Modules\PilotOfflineRollout\Domain\OfflineOperationPolicy;
use Qmdb\Modules\PilotOfflineRollout\Domain\OfflinePackageCryptography;
use Qmdb\Modules\PilotOfflineRollout\Domain\PilotRolloutLifecycle;

final class P13DomainTest extends TestCase
{
    public function testCanonicalJsonAndPackageCryptographyRejectTampering(): void
    {
        $canonical = new CanonicalJson();
        self::assertSame('{"a":{"c":3,"d":4},"b":2}', $canonical->encode(['b' => 2, 'a' => ['d' => 4, 'c' => 3]]));
        $crypto = new OfflinePackageCryptography(str_repeat('s', 32), str_repeat('e', 32), false);
        $manifest = $canonical->encode(['package' => 'fixture', 'version' => 1]);
        $signature = $crypto->sign($manifest);
        self::assertTrue($crypto->verify($manifest, $signature));
        self::assertFalse($crypto->verify($manifest . 'x', $signature));
        $encrypted = $crypto->encrypt('private package', 'fixture-associated-data');
        self::assertSame('private package', $crypto->decrypt($encrypted['ciphertext'], $encrypted['nonce'], 'fixture-associated-data'));
        self::expectException(\DomainException::class);
        $crypto->decrypt($encrypted['ciphertext'] . 'x', $encrypted['nonce'], 'fixture-associated-data');
    }

    public function testDeviceSignatureBindsMethodPathTimestampNonceBodyDeviceAndPackage(): void
    {
        $signature = new DeviceRequestSignature();
        $seed = str_repeat('k', 32);
        $pair = sodium_crypto_sign_seed_keypair($seed);
        $canonical = $signature->canonical('post', '/offline/v1/sync/sessions', time(), str_repeat('n', 16), hash('sha256', '{}'), 'device', 'package');
        $detached = sodium_crypto_sign_detached($canonical, sodium_crypto_sign_secretkey($pair));
        self::assertTrue($signature->verify($canonical, $detached, sodium_crypto_sign_publickey($pair)));
        self::assertFalse($signature->verify($canonical . 'tampered', $detached, sodium_crypto_sign_publickey($pair)));
    }

    #[DataProvider('allowedOperations')]
    public function testExactOfflineOperationAllowlist(string $operation): void
    {
        (new OfflineOperationPolicy())->assertAllowed($operation, ['entity_id' => 'safe']);
        self::addToAssertionCount(1);
    }

    /** @return iterable<string,array{string}> */
    public static function allowedOperations(): iterable
    {
        foreach (OfflineOperationPolicy::ALLOWED as $operation) {
            yield $operation => [$operation];
        }
    }

    public function testOfflinePolicyRejectsUnknownAndSensitivePayloads(): void
    {
        $policy = new OfflineOperationPolicy();
        try {
            $policy->assertAllowed('FINALIZE_RESULT', []);
            self::fail('Unknown operation was accepted.');
        } catch (\DomainException) {
            self::addToAssertionCount(1);
        }
        $this->expectException(\DomainException::class);
        $policy->assertAllowed('OPERATIONAL_NOTE_RECORDED', ['session_token' => 'forbidden']);
    }

    public function testPilotAndWaveLifecyclesAreClosed(): void
    {
        $lifecycle = new PilotRolloutLifecycle();
        $lifecycle->assertPilotTransition('DRAFT', 'READINESS_REVIEW');
        $lifecycle->assertWaveTransition('ACTIVE', 'CONTAINED');
        $this->expectException(\DomainException::class);
        $lifecycle->assertPilotTransition('DRAFT', 'ACTIVE');
    }
}
