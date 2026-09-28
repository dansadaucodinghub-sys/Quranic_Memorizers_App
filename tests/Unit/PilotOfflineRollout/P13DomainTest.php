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
use Qmdb\Modules\PilotOfflineRollout\Application\MappedOfflineOperationHandler;
use Qmdb\Modules\PilotOfflineRollout\Application\OfflineOperationDispatcher;
use Qmdb\Modules\PilotOfflineRollout\Application\OfflineOperationContext;
use Qmdb\Modules\PilotOfflineRollout\Application\OfflineOperationResult;
use Qmdb\Modules\PilotOfflineRollout\Application\P13RouteRuntimeCatalog;
use Qmdb\Modules\PilotOfflineRollout\Application\P13MaintenanceService;
use Qmdb\Modules\PilotOfflineRollout\Application\P13MutationRequestParser;
use Qmdb\Modules\PilotOfflineRollout\Infrastructure\Persistence\MySqlPilotOfflineRolloutRepository;
use Qmdb\Shared\Identifier\UuidV7;

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

    public function testRouteRuntimeCatalogCoversExactFrozenSurface(): void
    {
        $entries = (new P13RouteRuntimeCatalog())->entries();
        self::assertCount(62, $entries);
        self::assertCount(40, P13RouteRuntimeCatalog::MUTATIONS);
        self::assertCount(35, P13RouteRuntimeCatalog::BROWSER_MUTATIONS);
        foreach ($entries as $route => $entry) {
            self::assertNotSame('', $entry['controller'], $route);
            self::assertNotSame('', $entry['application_service'], $route);
            self::assertNotSame('', $entry['runtime_capability'], $route);
        }
    }

    public function testDispatcherRequiresExactlyOneConcreteHandlerPerAllowedOperation(): void
    {
        $handlers = [];
        foreach (OfflineOperationPolicy::ALLOWED as $operation) {
            $handlers[] = new MappedOfflineOperationHandler(
                $operation,
                in_array($operation, ['SCORE_DRAFT_SAVED', 'SCORE_SHEET_SUBMITTED'], true) ? 'P6_SCORING' : (str_starts_with($operation, 'PARTICIPANT_') || str_starts_with($operation, 'PERFORMANCE_') ? 'P7_LIVE' : 'P13_OFFLINE'),
                static fn (OfflineOperationContext $context): OfflineOperationResult => OfflineOperationResult::accepted($context->entityId, 2, 'APPLIED'),
            );
        }
        $dispatcher = new OfflineOperationDispatcher($handlers);
        self::assertCount(13, $dispatcher->registrations());

        $this->expectException(\LogicException::class);
        new OfflineOperationDispatcher(array_slice($handlers, 0, 12));
    }

    public function testDispatcherRejectsDuplicateHandlerRegistration(): void
    {
        $handlers = [];
        foreach (OfflineOperationPolicy::ALLOWED as $operation) {
            $handlers[] = new MappedOfflineOperationHandler($operation, 'P13_OFFLINE', static fn (OfflineOperationContext $context): OfflineOperationResult => OfflineOperationResult::accepted($context->entityId, 2, 'APPLIED'));
        }
        $handlers[] = $handlers[0];
        $this->expectException(\LogicException::class);
        new OfflineOperationDispatcher($handlers);
    }

    public function testOfflineOperationResultIsClosedOverAllProtocolOutcomes(): void
    {
        $entity = UuidV7::generate();
        self::assertSame('ACCEPTED', OfflineOperationResult::accepted($entity, 2, 'APPLIED')->status);
        self::assertSame('DUPLICATE', OfflineOperationResult::duplicate($entity, 2, 'APPLIED')->status);
        self::assertSame('CONFLICT', OfflineOperationResult::conflict($entity, 'VERSION_MISMATCH', 1)->status);
        self::assertSame('REJECTED', OfflineOperationResult::rejected($entity, 'PAYLOAD_INVALID')->status);
    }

    public function testEveryP13MaintenanceOperationHasAnOperationSpecificRepositoryHandler(): void
    {
        self::assertCount(17, P13MaintenanceService::HANDLERS);
        self::assertNotContains('verifyRuntime', P13MaintenanceService::HANDLERS);
        self::assertCount(count(P13MaintenanceService::HANDLERS), array_unique(P13MaintenanceService::HANDLERS));
        foreach (P13MaintenanceService::HANDLERS as $operation => $method) {
            self::assertTrue(method_exists(MySqlPilotOfflineRolloutRepository::class, $method), $operation);
            self::assertTrue((new \ReflectionMethod(MySqlPilotOfflineRolloutRepository::class, $method))->isPublic(), $operation);
        }
    }

    public function testAdministrativeParserTreatsAnUncheckedReadinessCheckboxAsFalse(): void
    {
        $parser = new P13MutationRequestParser();
        self::assertFalse($parser->boolean([], 'blocking'));
        self::assertTrue($parser->boolean(['blocking' => '1'], 'blocking'));
    }
}
