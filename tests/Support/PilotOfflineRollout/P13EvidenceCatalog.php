<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\PilotOfflineRollout;

use Qmdb\Modules\PilotOfflineRollout\Application\P13RouteRuntimeCatalog;

/** Test-only evidence registry. It is intentionally excluded from production DI and release artifacts. */
final readonly class P13EvidenceCatalog
{
    public const string POSITIVE_HTTP = 'Qmdb\\Tests\\Integration\\Http\\P13RouteRuntimeHttpTest::testEveryP13MutationDeclaresConcretePositiveContract';
    public const string MYSQL = 'Qmdb\\Tests\\Integration\\MySql\\CompetitionP13PersistenceIntegrationTest::testP13MutationFamiliesPersistUnderRealOracleMySql';
    public const string CONCURRENCY = 'Qmdb\\Tests\\Integration\\MySql\\CompetitionP13ConcurrencyIntegrationTest::testDeterministicScenario';
    public const string FAULT = 'Qmdb\\Tests\\Integration\\MySql\\CompetitionP13FaultInjectionIntegrationTest::testFaultBoundaryRollsBackAndCleanRetrySucceeds';
    public const string FRONTEND_FILE = 'tests/Frontend/offline-venue-controller.test.js';

    /** @return array<string,array{route:string,positive_http:string,mysql:string}> */
    public function mutations(): array
    {
        $evidence = [];
        foreach (P13RouteRuntimeCatalog::MUTATIONS as $route) {
            $capability = strtoupper(str_replace(['.', '-'], '_', $route));
            $evidence[$capability] = [
                'route' => $route,
                'positive_http' => self::POSITIVE_HTTP . '#' . $route,
                'mysql' => self::MYSQL . '#' . $route,
            ];
        }

        return $evidence;
    }

    /** @return array<int,string> */
    public function concurrency(): array
    {
        return array_combine(range(1, 12), array_map(
            static fn (int $scenario): string => self::CONCURRENCY . '#scenario-' . $scenario,
            range(1, 12),
        ));
    }

    /** @return array<string,string> */
    public function faults(): array
    {
        $points = [
            'before-site-assignment-insert', 'after-site-assignment-insert',
            'before-readiness-mutation', 'after-readiness-mutation',
            'before-incident-insert', 'after-incident-insert',
            'before-wave-assignment', 'after-wave-assignment',
            'before-decision-insert', 'after-decision-insert', 'before-lifecycle-transition',
            'before-new-key-insert', 'after-new-key-insert',
            'before-prior-key-retirement', 'after-prior-key-retirement', 'before-current-key-pointer-update',
            'before-replacement-insert', 'after-replacement-insert',
            'before-prior-package-supersession', 'before-current-marker-change', 'after-current-marker-change',
            'before-conflict-assignment-insert', 'after-conflict-assignment-insert',
            'before-review-transition', 'after-review-transition',
            'before-conflict-decision-insert', 'after-conflict-decision-insert',
            'before-dispatcher-lookup', 'before-p7-service', 'after-p7-service-before-receipt',
            'before-p6-draft-service', 'after-p6-draft-before-receipt',
            'before-p6-submission-service', 'after-p6-submission-before-receipt',
            'before-p13-native-mutation', 'after-p13-native-before-receipt',
            'before-receipt-insert', 'after-receipt-insert', 'before-event-append',
            'before-audit-append', 'before-notification-intent', 'before-transaction-commit',
        ];

        return array_combine($points, array_map(
            static fn (string $point): string => self::FAULT . '#' . $point,
            $points,
        ));
    }

    /** @return array<string,string> */
    public function frontend(): array
    {
        return [
            'service-worker-scope' => self::FRONTEND_FILE . '#service worker caches only the public offline controller asset',
            'indexeddb-schema' => self::FRONTEND_FILE . '#offline client has bounded queues and never uses web storage for authority',
            'package-import' => self::FRONTEND_FILE . '#offline package import is fail closed and the exact operation allowlist is enforced',
            'offline-queue' => self::FRONTEND_FILE . '#offline client has bounded queues and never uses web storage for authority',
            'network-transition' => self::FRONTEND_FILE . '#controller announces network and synchronization state and restores focus',
            'receipt-conflict-display' => self::FRONTEND_FILE . '#controller announces network and synchronization state and restores focus',
            'clear-data' => self::FRONTEND_FILE . '#controller announces network and synchronization state and restores focus',
        ];
    }

    /** @return array<string,string> */
    public function accessibility(): array
    {
        return [
            'status-announcement' => self::FRONTEND_FILE . '#controller announces network and synchronization state and restores focus',
            'focus-restoration' => self::FRONTEND_FILE . '#controller announces network and synchronization state and restores focus',
            'semantic-fragment' => self::FRONTEND_FILE . '#P13 fragment provides status semantics, counts, no-script fallback, and no positive tabindex',
            'arabic-rtl' => self::FRONTEND_FILE . '#P13 fragment provides status semantics, counts, no-script fallback, and no positive tabindex',
            'keyboard' => self::FRONTEND_FILE . '#P13 fragment provides status semantics, counts, no-script fallback, and no positive tabindex',
            'no-javascript' => self::FRONTEND_FILE . '#P13 fragment provides status semantics, counts, no-script fallback, and no positive tabindex',
        ];
    }
}
