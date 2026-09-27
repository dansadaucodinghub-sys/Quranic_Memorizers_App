<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\MySql;

use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Qmdb\Tests\Support\MySql\MySqlIntegrationTestCase;

#[Group('P13')]
#[Group('P13Performance')]
final class CompetitionP13QueryPlanIntegrationTest extends MySqlIntegrationTestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function significantQueryProvider(): iterable
    {
        yield 'pilot readiness reconciliation' => [
            "SELECT id FROM pilot_site_readiness_checks WHERE workspace_id=1 AND pilot_site_id=1 AND check_code='DEVICE' AND expires_at>UTC_TIMESTAMP(6) ORDER BY expires_at DESC,id DESC LIMIT 1",
            'ix_p13_readiness_current',
        ];
        yield 'rollout wave processor' => [
            "SELECT id FROM rollout_waves WHERE status_code='APPROVED' AND planned_start_at<=UTC_TIMESTAMP(6) ORDER BY planned_start_at,id LIMIT 100",
            'ix_p13_rollout_wave_due',
        ];
        yield 'rollout assignment readiness' => [
            "SELECT id FROM rollout_wave_assignments WHERE rollout_wave_id=1 AND status_code='READY' ORDER BY id LIMIT 500",
            'ix_p13_rollout_assignment_wave',
        ];
        yield 'active device key lookup' => [
            "SELECT id FROM offline_device_keys WHERE workspace_id=1 AND device_id=1 AND status_code='ACTIVE' AND valid_from<=UTC_TIMESTAMP(6) AND valid_until>UTC_TIMESTAMP(6) ORDER BY valid_from DESC,id DESC LIMIT 1",
            'ix_p13_device_key_active',
        ];
        yield 'package entity generation' => [
            'SELECT id FROM offline_package_entities WHERE workspace_id=1 AND package_id=1 ORDER BY sort_sequence,id LIMIT 1000',
            'ix_p13_package_entity_list',
        ];
        yield 'pending synchronization work' => [
            "SELECT id FROM offline_sync_changes WHERE workspace_id=1 AND status_code='RECEIVED' ORDER BY received_at,id LIMIT 100",
            'ix_p13_sync_change_pending',
        ];
        yield 'synchronization receipt pagination' => [
            'SELECT id FROM offline_sync_receipts WHERE workspace_id=1 AND sync_session_id=1 ORDER BY local_sequence,id LIMIT 100',
            'ix_p13_sync_receipt_session',
        ];
        yield 'reviewer conflict queue' => [
            "SELECT id FROM offline_conflict_assignments WHERE workspace_id=1 AND reviewer_account_id=1 AND status_code='ACTIVE' ORDER BY assigned_at,id LIMIT 100",
            'ix_p13_conflict_assignment',
        ];
        yield 'expired nonce cleanup' => [
            'SELECT id FROM offline_device_nonces WHERE expires_at<=UTC_TIMESTAMP(6) ORDER BY expires_at,id LIMIT 100',
            'ix_p13_device_nonce_expiry',
        ];
        yield 'open pilot incidents' => [
            "SELECT id FROM pilot_incidents WHERE workspace_id=1 AND pilot_site_id=1 AND status_code='OPEN' ORDER BY severity_code,id LIMIT 100",
            'ix_p13_pilot_incident_open',
        ];
        yield 'rollout health snapshots' => [
            'SELECT id FROM rollout_health_snapshots WHERE rollout_plan_id=1 AND rollout_wave_id=1 ORDER BY source_cutoff_at DESC,id DESC LIMIT 1',
            'ix_p13_rollout_health_wave',
        ];
        yield 'offline operation evidence' => [
            'SELECT id FROM offline_operation_events WHERE workspace_id=1 AND venue_id=1 ORDER BY occurred_at,id LIMIT 100',
            'ix_p13_operation_event',
        ];
    }

    #[DataProvider('significantQueryProvider')]
    public function testSignificantP13QueryExposesItsBoundedCompositeIndex(string $query, string $expectedIndex): void
    {
        $statement = $this->provider()->connection()->query('EXPLAIN ' . $query);
        self::assertNotFalse($statement);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        self::assertNotSame([], $rows);

        $availableIndexes = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                self::fail('MySQL returned a non-row value for an EXPLAIN query.');
            }
            foreach (['possible_keys', 'key'] as $field) {
                $indexes = $row[$field] ?? null;
                if (!is_string($indexes) || $indexes === '') {
                    continue;
                }
                array_push($availableIndexes, ...explode(',', $indexes));
            }
        }

        self::assertContains($expectedIndex, array_values(array_unique($availableIndexes)));
    }
}
