<?php

declare(strict_types=1);

namespace Qmdb\Modules\ProductionHardening\Infrastructure\Persistence;

use PDO;
use PDOStatement;
use Qmdb\Modules\ProductionHardening\Application\WebhookTransport;
use Qmdb\Modules\ProductionHardening\Domain\BoundedRetryPolicy;
use Qmdb\Modules\ProductionHardening\Domain\IntegrationSecretBox;
use Qmdb\Modules\ProductionHardening\Domain\WebhookEndpointPolicy;
use Qmdb\Modules\ProductionHardening\Domain\WebhookSignature;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class P12MaintenanceService
{
    private const int BATCH = 25;

    public function __construct(
        private MySqlProductionHardeningRepository $repository,
        private WebhookEndpointPolicy $endpoints,
        private WebhookSignature $signatures,
        private IntegrationSecretBox $secrets,
        private BoundedRetryPolicy $retry,
        private WebhookTransport $transport,
    ) {
    }

    /** @return array{examined:int,changed:int} */
    public function processOutbox(bool $dryRun): array
    {
        $database = $this->repository->database();
        $database->beginTransaction();
        try {
            $rows = $this->rows($database, "SELECT id,public_id,workspace_id,aggregate_kind,aggregate_public_id,aggregate_version,event_code,payload_json,payload_hash,correlation_id FROM outbox_events WHERE status_code IN ('PENDING','RETRY') AND available_at<=UTC_TIMESTAMP(6) ORDER BY FIELD(priority_code,'CRITICAL','HIGH','NORMAL','BULK'),id LIMIT " . self::BATCH . ' FOR UPDATE SKIP LOCKED');
            if ($dryRun) {
                $database->rollBack();
                return ['examined' => count($rows), 'changed' => 0];
            }
            foreach ($rows as $row) {
                $event = UuidV7::generate();
                $statement = $this->prepare($database, "INSERT INTO integration_events (public_id,workspace_id,event_code,source_kind,source_public_id,source_version,classification_code,payload_json,payload_hash,correlation_id,occurred_at,created_at) VALUES (:public,:workspace,:event,:kind,:subject,:version,'INTERNAL',:payload,:hash,:correlation,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");
                $statement->execute([':public' => $event->toBinary(), ':workspace' => $row['workspace_id'], ':event' => $row['event_code'], ':kind' => $row['aggregate_kind'], ':subject' => $row['aggregate_public_id'], ':version' => $row['aggregate_version'], ':payload' => $row['payload_json'], ':hash' => $row['payload_hash'], ':correlation' => $row['correlation_id']]);
                $eventId = (int) $database->lastInsertId();
                $delivery = $this->prepare($database, "INSERT INTO webhook_deliveries (public_id,subscription_id,integration_event_id,delivery_key,status_code,attempt_count,available_at,leased_until,lease_token,delivered_at,dead_lettered_at,last_error_code,created_at,updated_at) SELECT UUID_TO_BIN(UUID()),s.id,:event,UNHEX(SHA2(CONCAT(s.id,':',:event_key),256)),'PENDING',0,UTC_TIMESTAMP(6),NULL,NULL,NULL,NULL,NULL,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6) FROM webhook_subscriptions s WHERE s.status_code='ACTIVE' AND s.event_code=:event_code AND (s.workspace_id IS NULL OR s.workspace_id<=>:workspace)");
                $delivery->execute([':event' => $eventId, ':event_key' => $event->toString(), ':event_code' => $row['event_code'], ':workspace' => $row['workspace_id']]);
                $this->prepare($database, "UPDATE outbox_events SET status_code='PUBLISHED',published_at=UTC_TIMESTAMP(6),leased_until=NULL,lease_token=NULL WHERE id=:id")->execute([':id' => $row['id']]);
            }
            $database->commit();
            return ['examined' => count($rows), 'changed' => count($rows)];
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    /** @return array{examined:int,changed:int} */
    public function processNotifications(bool $dryRun): array
    {
        $database = $this->repository->database();
        $database->beginTransaction();
        try {
            $rows = $this->rows($database, "SELECT d.id,d.public_id,d.channel_code,d.attempt_count FROM notification_deliveries d WHERE d.status_code IN ('PENDING','RETRY') AND d.available_at<=UTC_TIMESTAMP(6) ORDER BY d.id LIMIT " . self::BATCH . ' FOR UPDATE SKIP LOCKED');
            if ($dryRun) {
                $database->rollBack();
                return ['examined' => count($rows), 'changed' => 0];
            }
            foreach ($rows as $row) {
                $attempt = $this->integer($row, 'attempt_count') + 1;
                $delivered = $row['channel_code'] === 'IN_APP';
                $status = $delivered ? 'DELIVERED' : ($this->retry->shouldRetry($attempt, null) ? 'RETRY' : 'DEAD_LETTER');
                $delay = $this->retry->delaySeconds($attempt, bin2hex($this->text($row, 'public_id')));
                $this->prepare($database, 'INSERT INTO notification_delivery_attempts (public_id,delivery_id,attempt_number,outcome_code,provider_correlation_hash,response_class,error_code,started_at,completed_at) VALUES (:public,:delivery,:attempt,:outcome,NULL,:response,:error,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))')
                    ->execute([':public' => UuidV7::generate()->toBinary(), ':delivery' => $row['id'], ':attempt' => $attempt, ':outcome' => $delivered ? 'DELIVERED' : ($status === 'RETRY' ? 'TRANSIENT_FAILURE' : 'PERMANENT_FAILURE'), ':response' => $delivered ? 'IN_APP' : 'PROVIDER_UNAVAILABLE', ':error' => $delivered ? null : 'EMAIL_PROVIDER_UNAVAILABLE']);
                $this->prepare($database, "UPDATE notification_deliveries SET status_code=:status,attempt_count=:attempt,available_at=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL :delay SECOND),delivered_at=IF(:delivered=1,UTC_TIMESTAMP(6),NULL),last_error_code=:error,updated_at=UTC_TIMESTAMP(6) WHERE id=:id")
                    ->execute([':status' => $status, ':attempt' => $attempt, ':delay' => $delay, ':delivered' => $delivered ? 1 : 0, ':error' => $delivered ? null : 'EMAIL_PROVIDER_UNAVAILABLE', ':id' => $row['id']]);
                if ($status === 'DEAD_LETTER') {
                    $this->prepare($database, "INSERT INTO notification_dead_letters (public_id,delivery_id,reason_code,safe_context_json,status_code,created_at,resolved_at) VALUES (:public,:delivery,'RETRY_EXHAUSTED',JSON_OBJECT(),'OPEN',UTC_TIMESTAMP(6),NULL)")->execute([':public' => UuidV7::generate()->toBinary(), ':delivery' => $row['id']]);
                }
            }
            $database->commit();
            return ['examined' => count($rows), 'changed' => count($rows)];
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    /** @return array{examined:int,changed:int} */
    public function processWebhooks(bool $dryRun): array
    {
        $database = $this->repository->database();
        /** @var list<array<string,mixed>> $rows */
        $rows = [];
        $database->beginTransaction();
        try {
            $rows = $this->rows($database, "SELECT d.id,d.public_id,d.attempt_count,s.endpoint_url,s.signing_key_id,s.signing_secret_ciphertext,e.payload_json FROM webhook_deliveries d INNER JOIN webhook_subscriptions s ON s.id=d.subscription_id AND s.status_code='ACTIVE' INNER JOIN integration_events e ON e.id=d.integration_event_id WHERE d.status_code IN ('PENDING','RETRY') AND d.available_at<=UTC_TIMESTAMP(6) ORDER BY d.id LIMIT " . self::BATCH . ' FOR UPDATE SKIP LOCKED');
            if ($dryRun) {
                $database->rollBack();
                return ['examined' => count($rows), 'changed' => 0];
            }
            foreach ($rows as &$row) {
                $leaseToken = UuidV7::generate()->toBinary();
                $this->prepare($database, "UPDATE webhook_deliveries SET status_code='PROCESSING',leased_until=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 60 SECOND),lease_token=:lease,updated_at=UTC_TIMESTAMP(6) WHERE id=:id")
                    ->execute([':lease' => $leaseToken, ':id' => $row['id']]);
                $row['lease_token'] = $leaseToken;
            }
            unset($row);
            $database->commit();
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }

        $changed = 0;
        foreach ($rows as $row) {
            if ($this->deliverClaimedWebhook($database, $row)) {
                ++$changed;
            }
        }
        return ['examined' => count($rows), 'changed' => $changed];
    }

    /**
     * Provider I/O is deliberately completed before the short result-recording
     * transaction starts. The lease token prevents stale workers from writing
     * an outcome after reconciliation has reclaimed the delivery.
     *
     * @param array<string,mixed> $row
     */
    private function deliverClaimedWebhook(PDO $database, array $row): bool
    {
        $payload = $this->text($row, 'payload_json');
        $deliveryId = UuidV7::fromBinary($this->text($row, 'public_id'))->toString();
        $timestamp = time();
        $keyId = $this->text($row, 'signing_key_id');
        $secret = null;
        $signature = '';
        try {
            $endpoint = $this->text($row, 'endpoint_url');
            $this->endpoints->assertAllowed($endpoint);
            $secret = $this->secrets->decrypt($this->text($row, 'signing_secret_ciphertext'));
            $signature = $this->signatures->sign($keyId, $secret, $deliveryId, $timestamp, $payload);
            $result = $this->transport->send($endpoint, $payload, [
                'Content-Type' => 'application/json', 'X-QMDB-Delivery-ID' => $deliveryId,
                'X-QMDB-Timestamp' => (string) $timestamp, 'X-QMDB-Signature' => $signature,
                'X-QMDB-Key-ID' => $keyId,
            ], 5);
        } catch (\Throwable) {
            $result = ['status' => 0, 'error_code' => 'TRANSPORT_REJECTED'];
        } finally {
            if (is_string($secret) && $secret !== '') {
                sodium_memzero($secret);
            }
        }

        $attempt = $this->integer($row, 'attempt_count') + 1;
        $success = $result['status'] >= 200 && $result['status'] < 300;
        $retry = !$success && $this->retry->shouldRetry(
            $attempt,
            $result['status'] === 0 ? null : $result['status'],
        );
        $status = $success ? 'DELIVERED' : ($retry ? 'RETRY' : 'DEAD_LETTER');
        $delay = $this->retry->delaySeconds($attempt, $deliveryId);

        $database->beginTransaction();
        try {
            $current = $this->prepare(
                $database,
                "SELECT id FROM webhook_deliveries WHERE id=:id AND status_code='PROCESSING' AND lease_token=:lease FOR UPDATE",
            );
            $current->execute([':id' => $row['id'], ':lease' => $row['lease_token']]);
            if ($current->fetchColumn() === false) {
                $database->rollBack();
                return false;
            }
            $this->prepare($database, 'INSERT INTO webhook_delivery_attempts (public_id,delivery_id,attempt_number,key_id,signature_digest,request_timestamp,response_status,response_class,error_code,started_at,completed_at) VALUES (:public,:delivery,:attempt,:key,:signature,FROM_UNIXTIME(:timestamp),:status,:class,:error,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))')
                ->execute([':public' => UuidV7::generate()->toBinary(), ':delivery' => $row['id'], ':attempt' => $attempt, ':key' => $keyId, ':signature' => hash('sha256', $signature, true), ':timestamp' => $timestamp, ':status' => $result['status'] === 0 ? null : $result['status'], ':class' => $success ? 'SUCCESS' : ($retry ? 'TRANSIENT_FAILURE' : 'PERMANENT_FAILURE'), ':error' => $result['error_code']]);
            $this->prepare($database, 'UPDATE webhook_deliveries SET status_code=:status,attempt_count=:attempt,available_at=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL :delay SECOND),leased_until=NULL,lease_token=NULL,delivered_at=IF(:delivered=1,UTC_TIMESTAMP(6),NULL),dead_lettered_at=IF(:dead=1,UTC_TIMESTAMP(6),NULL),last_error_code=:error,updated_at=UTC_TIMESTAMP(6) WHERE id=:id')
                ->execute([':status' => $status, ':attempt' => $attempt, ':delay' => $delay, ':delivered' => $success ? 1 : 0, ':dead' => $status === 'DEAD_LETTER' ? 1 : 0, ':error' => $result['error_code'], ':id' => $row['id']]);
            $database->commit();
            return true;
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    /** @return array{examined:int,changed:int} */
    public function reconcile(): array
    {
        $changed = 0;
        foreach (['webhook_deliveries', 'notification_deliveries', 'outbox_events'] as $table) {
            $statement = $this->repository->database()->prepare("UPDATE {$table} SET status_code='RETRY',leased_until=NULL,lease_token=NULL,available_at=UTC_TIMESTAMP(6) WHERE status_code='PROCESSING' AND leased_until<UTC_TIMESTAMP(6) LIMIT 100");
            if (!$statement instanceof PDOStatement) {
                throw new \RuntimeException('P12 reconciliation statement failed.');
            }
            $statement->execute();
            $changed += $statement->rowCount();
        }
        return ['examined' => $changed, 'changed' => $changed];
    }

    /** @return array{examined:int,changed:int} */
    public function retention(bool $dryRun): array
    {
        $assessment = $this->repository->retentionDryRun(100);
        if ($dryRun || $assessment['eligible'] === 0) {
            return ['examined' => $assessment['examined'], 'changed' => 0];
        }
        $database = $this->repository->database();
        $database->beginTransaction();
        try {
            $rows = $this->rows($database, "SELECT n.id,n.public_id,p.id AS policy_id FROM notifications n INNER JOIN retention_policy_records p ON p.policy_code='NOTIFICATION_OPERATIONAL' AND p.status_code='ACTIVE' WHERE n.created_at<DATE_SUB(UTC_TIMESTAMP(6),INTERVAL p.retention_days DAY) AND NOT EXISTS(SELECT 1 FROM data_holds h WHERE h.status_code='ACTIVE' AND h.subject_kind='NOTIFICATION' AND (h.subject_public_id IS NULL OR h.subject_public_id=n.public_id) AND (h.expires_at IS NULL OR h.expires_at>UTC_TIMESTAMP(6))) ORDER BY n.id LIMIT 100 FOR UPDATE SKIP LOCKED");
            foreach ($rows as $row) {
                $this->prepare($database, "UPDATE notifications SET safe_subject='[RETAINED_METADATA_ONLY]',safe_body='[RETAINED_METADATA_ONLY]',source_uri=NULL,status_code='EXPIRED',updated_at=UTC_TIMESTAMP(6) WHERE id=:id")->execute([':id' => $row['id']]);
                $subject = bin2hex($this->text($row, 'public_id'));
                $this->prepare($database, "INSERT INTO anonymization_events (public_id,workspace_id,privacy_request_id,retention_policy_id,subject_kind,subject_reference_hash,disposition_code,affected_records,manifest_hash,operation_key,performed_at) VALUES (:public,NULL,NULL,:policy,'NOTIFICATION',UNHEX(SHA2(:subject_hash,256)),'ANONYMIZED',1,UNHEX(SHA2('NOTIFICATION_MINIMIZED',256)),UNHEX(SHA2(CONCAT(:operation_subject,':retention'),256)),UTC_TIMESTAMP(6))")
                    ->execute([':public' => UuidV7::generate()->toBinary(), ':policy' => $row['policy_id'], ':subject_hash' => $subject, ':operation_subject' => $subject]);
            }
            $database->commit();
            return ['examined' => count($rows), 'changed' => count($rows)];
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    /** @return array{examined:int,changed:int} */
    public function cleanup(bool $dryRun): array
    {
        $database = $this->repository->database();
        $examined = $this->scalar($database, "SELECT COUNT(*) FROM idempotency_records WHERE expires_at<=UTC_TIMESTAMP(6)")
            + $this->scalar($database, "SELECT COUNT(*) FROM operational_metric_samples WHERE expires_at<=UTC_TIMESTAMP(6)");
        if ($dryRun) {
            return ['examined' => $examined, 'changed' => 0];
        }
        $first = $database->exec("DELETE FROM idempotency_records WHERE expires_at<=UTC_TIMESTAMP(6) LIMIT 100");
        $second = $database->exec("DELETE FROM operational_metric_samples WHERE expires_at<=UTC_TIMESTAMP(6) LIMIT 100");
        return ['examined' => $examined, 'changed' => (is_int($first) ? $first : 0) + (is_int($second) ? $second : 0)];
    }

    /** @return array{examined:int,changed:int} */
    public function verifyAudit(): array
    {
        $invalid = $this->scalar($this->repository->database(), "SELECT COUNT(*) FROM audit_events e LEFT JOIN audit_events p ON p.stream_code=e.stream_code AND p.sequence_number=e.sequence_number-1 WHERE e.sequence_number>1 AND (p.id IS NULL OR e.previous_hash<>p.event_hash)");
        if ($invalid !== 0) {
            throw new \RuntimeException('P12 audit lineage verification found a gap or hash-link mismatch.');
        }
        return ['examined' => $this->scalar($this->repository->database(), 'SELECT COUNT(*) FROM audit_events'), 'changed' => 0];
    }

    /** @return array{examined:int,changed:int} */
    public function verifyBackups(): array
    {
        $database = $this->repository->database();
        $invalid = $this->scalar($database, "SELECT COUNT(*) FROM backup_artifacts WHERE OCTET_LENGTH(storage_reference_hash)<>32 OR OCTET_LENGTH(artifact_checksum)<>32 OR encryption_key_id='' OR source_completed_at<source_started_at OR expires_at<=source_completed_at");
        if ($invalid !== 0) {
            throw new \RuntimeException('P12 backup metadata verification found invalid recovery evidence.');
        }

        return ['examined' => $this->scalar($database, 'SELECT COUNT(*) FROM backup_artifacts'), 'changed' => 0];
    }

    /** @return array{examined:int,changed:int} */
    public function verifyWebhooks(): array
    {
        $database = $this->repository->database();
        $rows = $this->rows(
            $database,
            "SELECT endpoint_url,signing_secret_ciphertext FROM webhook_subscriptions WHERE status_code='ACTIVE' ORDER BY id LIMIT 100",
        );
        foreach ($rows as $row) {
            $this->endpoints->assertAllowed($this->text($row, 'endpoint_url'));
            $secret = $this->secrets->decrypt($this->text($row, 'signing_secret_ciphertext'));
            sodium_memzero($secret);
        }

        return ['examined' => count($rows), 'changed' => 0];
    }

    /** @return array{examined:int,changed:int} */
    public function verifyRestores(): array
    {
        $database = $this->repository->database();
        $invalid = $this->scalar($database, "SELECT COUNT(*) FROM restore_verification_runs WHERE outcome_code='PASS' AND (isolation_verified<>1 OR schema_verified<>1 OR audit_verified<>1 OR destroyed_at IS NULL OR completed_at<started_at OR destroyed_at<completed_at)");
        if ($invalid !== 0) {
            throw new \RuntimeException('P12 restore evidence verification found an invalid successful run.');
        }

        return ['examined' => $this->scalar($database, 'SELECT COUNT(*) FROM restore_verification_runs'), 'changed' => 0];
    }

    /** @return list<array<string,mixed>> */
    private function rows(PDO $database, string $sql): array
    {
        $statement = $database->query($sql);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('P12 maintenance query failed.');
        }
        $rows = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            if (!is_array($row)) {
                throw new \RuntimeException('P12 maintenance query returned an invalid row.');
            }
            $normalized = [];
            foreach ($row as $key => $value) {
                if (!is_string($key)) {
                    throw new \RuntimeException('P12 maintenance row contained a non-string column key.');
                }
                $normalized[$key] = $value;
            }
            $rows[] = $normalized;
        }
        return $rows;
    }

    private function scalar(PDO $database, string $sql): int
    {
        $statement = $database->query($sql);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('P12 maintenance count failed.');
        }
        return (int) $statement->fetchColumn();
    }

    private function prepare(PDO $database, string $sql): PDOStatement
    {
        $statement = $database->prepare($sql);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('P12 maintenance statement failed.');
        }
        return $statement;
    }

    /** @param array<string,mixed> $row */
    private function integer(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        if (!is_int($value) && !(is_string($value) && preg_match('/\A[0-9]+\z/', $value) === 1)) {
            throw new \RuntimeException('P12 maintenance integer column is invalid.');
        }
        return (int) $value;
    }

    /** @param array<string,mixed> $row */
    private function text(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value)) {
            throw new \RuntimeException('P12 maintenance text column is invalid.');
        }
        return $value;
    }
}
