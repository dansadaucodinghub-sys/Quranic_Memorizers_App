<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\MySql;

use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\IdentityMultiFactor\Domain\AccountMfaPreferredMethod;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationMethod;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationTransactionPurpose;
use Qmdb\Modules\IdentityMultiFactor\Domain\EncryptedTotpSecret;
use Qmdb\Modules\IdentityMultiFactor\Domain\NormalizedRecoveryCode;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\IdentityMultiFactor\Domain\WebAuthnCeremonyPurpose;
use Qmdb\Modules\IdentityMultiFactor\Domain\WebAuthnChallenge;
use Qmdb\Modules\IdentityMultiFactor\Infrastructure\Persistence\MySqlIdentityMultiFactorRepository;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Schema\Migration\MigrationRegistry;
use Qmdb\Tests\Support\MySql\MySqlIntegrationTestCase;

#[Group('mysql')]
#[Group('MfaAbuse')]
final class P2IdentityMultiFactorIntegrationTest extends MySqlIntegrationTestCase
{
    private PDO $connection;
    private MySqlIdentityMultiFactorRepository $repository;
    private int $accountId;

    protected function setUp(): void
    {
        parent::setUp();
        $provider = $this->provider();
        $this->connection = $provider->connection();
        $this->rebuildSchema();
        $this->clearRows();
        $this->repository = new MySqlIdentityMultiFactorRepository($provider);
        $this->accountId = $this->seedAccount();
    }

    protected function tearDown(): void
    {
        $this->clearRows();
        parent::tearDown();
    }

    public function testAuthenticationTransactionAttemptsCompletionAndReplayAreOptimistic(): void
    {
        $now = new DateTimeImmutable('2026-08-27T12:00:00Z');
        $cookie = $this->repository->createTransaction(
            $this->accountId,
            null,
            AuthenticationTransactionPurpose::LOGIN_MFA,
            null,
            AuthenticationMethod::PASSWORD,
            [AuthenticationMethod::TOTP, AuthenticationMethod::RECOVERY_CODE],
            3,
            $now,
            $now->modify('+5 minutes'),
        );
        $transaction = $this->repository->findTransaction($cookie);
        self::assertNotNull($transaction);
        self::assertTrue($this->repository->recordTransactionFailure($transaction, $now));
        self::assertFalse($this->repository->recordTransactionFailure($transaction, $now));
        $updated = $this->repository->findTransaction($cookie);
        self::assertNotNull($updated);
        self::assertSame(1, $updated->attemptCount);
        self::assertTrue($this->repository->completeTransaction($updated, $now));
        self::assertFalse($this->repository->completeTransaction($updated, $now));
        self::assertFalse($this->repository->findTransaction($cookie)?->usableAt($now) ?? true);
    }

    public function testTotpCounterAndRecoveryCodesEnforceOneTimeState(): void
    {
        $now = new DateTimeImmutable('2026-08-27T12:00:00Z');
        $authenticator = $this->repository->createPendingTotp(
            $this->accountId,
            UuidV7::generate()->toString(),
            new EncryptedTotpSecret('ciphertext', random_bytes(24), 1),
            $now->modify('+10 minutes'),
            $now,
        );
        self::assertTrue($this->repository->confirmTotp($authenticator, 100, $now));
        $active = $this->repository->findActiveTotp($this->accountId);
        self::assertNotNull($active);
        self::assertTrue($this->repository->acceptTotpCounter($active, 101, $now));
        self::assertFalse($this->repository->acceptTotpCounter($active, 101, $now));

        $first = NormalizedRecoveryCode::fromInput('ABCD-EFGH-JKLM-NPQR-STVX')->hash('test-key');
        $second = NormalizedRecoveryCode::fromInput('WXYZ-2345-6789-ABCD-EFGH')->hash('test-key');
        $set = $this->repository->replaceRecoveryCodeSet($this->accountId, [$first, $second], $now);
        self::assertSame(2, $set->remainingCodes);
        self::assertTrue($this->repository->consumeRecoveryCode($set, $first, $now));
        self::assertFalse($this->repository->consumeRecoveryCode($set, $first, $now));
        self::assertSame(1, $this->repository->findActiveRecoveryCodeSet($this->accountId)?->remainingCodes);
    }

    public function testMfaPolicyPasskeyHandleAndCeremonyLifecycleArePersisted(): void
    {
        $now = new DateTimeImmutable('2026-08-27T12:00:00Z');
        $policy = $this->repository->findPolicy($this->accountId, true);
        self::assertFalse($policy->enabled());
        $pending = $this->repository->createPendingTotp(
            $this->accountId,
            UuidV7::generate()->toString(),
            new EncryptedTotpSecret('ciphertext', random_bytes(24), 1),
            $now->modify('+10 minutes'),
            $now,
        );
        self::assertTrue($this->repository->confirmTotp($pending, 200, $now));
        self::assertTrue($this->repository->enablePolicy($policy, AccountMfaPreferredMethod::TOTP, $now));
        self::assertTrue($this->repository->findPolicy($this->accountId)->enabled());

        $handle = $this->repository->findOrCreateUserHandle($this->accountId, $now);
        self::assertSame(32, strlen($handle));
        self::assertSame($handle, $this->repository->findOrCreateUserHandle($this->accountId, $now));
        self::assertSame($this->accountId, $this->repository->findAccountByUserHandle($handle));

        $challenge = WebAuthnChallenge::generate();
        $ceremony = $this->repository->createCeremony(
            $this->accountId,
            null,
            null,
            WebAuthnCeremonyPurpose::PASSKEY_LOGIN,
            $challenge,
            5,
            $now,
            $now->modify('+5 minutes'),
        );
        self::assertTrue($ceremony->accepts($challenge, $now));
        self::assertTrue($this->repository->consumeCeremony($ceremony, $now));
        self::assertFalse($this->repository->consumeCeremony($ceremony, $now));
    }

    public function testConcurrentRecoveryCodeConsumptionHasOneWinner(): void
    {
        $now = new DateTimeImmutable('2026-08-27T12:00:00Z');
        $target = NormalizedRecoveryCode::fromInput('ABCD-EFGH-JKLM-NPQR-STVX')->hash('test-key');
        $other = NormalizedRecoveryCode::fromInput('WXYZ-2345-6789-ABCD-EFGH')->hash('test-key');
        $this->repository->replaceRecoveryCodeSet($this->accountId, [$target, $other], $now);
        $payload = json_encode([
            'operation' => 'recovery_code',
            'account_id' => $this->accountId,
            'hash_hex' => bin2hex($target->toBinary()),
            'now' => $now->format(DATE_ATOM),
        ], JSON_THROW_ON_ERROR);

        $results = $this->runWorkers($payload, 2);
        sort($results);
        self::assertSame([false, true], $results);
        self::assertSame(1, $this->repository->findActiveRecoveryCodeSet($this->accountId)?->remainingCodes);
    }

    public function testConcurrentTotpCounterAcceptanceHasOneWinner(): void
    {
        $now = new DateTimeImmutable('2026-08-27T12:00:00Z');
        $pending = $this->repository->createPendingTotp(
            $this->accountId,
            UuidV7::generate()->toString(),
            new EncryptedTotpSecret('ciphertext', random_bytes(24), 1),
            $now->modify('+10 minutes'),
            $now,
        );
        self::assertTrue($this->repository->confirmTotp($pending, 300, $now));
        $payload = json_encode([
            'operation' => 'totp_counter',
            'account_id' => $this->accountId,
            'counter' => 301,
            'now' => $now->format(DATE_ATOM),
        ], JSON_THROW_ON_ERROR);

        $results = $this->runWorkers($payload, 2);
        sort($results);
        self::assertSame([false, true], $results);
        self::assertSame(301, $this->repository->findActiveTotp($this->accountId)?->lastAcceptedCounter);
    }

    public function testConcurrentAuthenticationTransactionCompletionHasOneWinner(): void
    {
        $now = new DateTimeImmutable('2026-08-27T12:00:00Z');
        $cookie = $this->repository->createTransaction(
            $this->accountId,
            null,
            AuthenticationTransactionPurpose::LOGIN_MFA,
            null,
            AuthenticationMethod::PASSWORD,
            [AuthenticationMethod::TOTP],
            3,
            $now,
            $now->modify('+5 minutes'),
        );
        $payload = json_encode([
            'operation' => 'authentication_transaction',
            'account_id' => $this->accountId,
            'cookie' => $cookie->revealForCookie(),
            'now' => $now->format(DATE_ATOM),
        ], JSON_THROW_ON_ERROR);

        $results = $this->runWorkers($payload, 2);
        sort($results);
        self::assertSame([false, true], $results);
        self::assertFalse($this->repository->findTransaction($cookie)?->usableAt($now) ?? true);
    }

    public function testStepUpGrantIsBoundExpiresAndHasOneConcurrentWinner(): void
    {
        $now = new DateTimeImmutable('2026-08-27T12:00:00Z');
        $sessionId = $this->seedSession($now);
        $grant = $this->repository->createGrant(
            $this->accountId,
            $sessionId,
            StepUpAction::MFA_DISABLE,
            AuthenticationAssuranceLevel::MULTI_FACTOR,
            $now,
            $now->modify('+5 minutes'),
        );
        self::assertTrue($grant->permits($this->accountId, $sessionId, StepUpAction::MFA_DISABLE, $now));
        self::assertFalse($grant->permits($this->accountId, $sessionId, StepUpAction::MFA_ENABLE, $now));
        self::assertFalse($grant->permits($this->accountId, $sessionId + 1, StepUpAction::MFA_DISABLE, $now));
        self::assertFalse($grant->permits(
            $this->accountId,
            $sessionId,
            StepUpAction::MFA_DISABLE,
            $now->modify('+5 minutes'),
        ));

        $results = $this->runWorkers(json_encode([
            'operation' => 'step_up_grant',
            'account_id' => $this->accountId,
            'session_id' => $sessionId,
            'action' => StepUpAction::MFA_DISABLE->value,
            'now' => $now->format(DATE_ATOM),
        ], JSON_THROW_ON_ERROR), 2);
        sort($results);
        self::assertSame([false, true], $results);
        self::assertNull($this->repository->findActiveGrant(
            $this->accountId,
            $sessionId,
            StepUpAction::MFA_DISABLE,
        ));
    }

    public function testConcurrentLastFactorRevocationsLeaveOneStrongFactorActive(): void
    {
        $now = new DateTimeImmutable('2026-08-27T12:00:00Z');
        $pending = $this->repository->createPendingTotp(
            $this->accountId,
            UuidV7::generate()->toString(),
            new EncryptedTotpSecret('ciphertext', random_bytes(24), 1),
            $now->modify('+10 minutes'),
            $now,
        );
        self::assertTrue($this->repository->confirmTotp($pending, 400, $now));
        $passkey = $this->repository->createPasskey(
            $this->accountId,
            UuidV7::generate()->toString(),
            random_bytes(32),
            random_bytes(77),
            0,
            null,
            ['internal'],
            true,
            true,
            'none',
            'Test passkey',
            $now,
        );
        $policy = $this->repository->findPolicy($this->accountId, true);
        self::assertTrue($this->repository->enablePolicy($policy, AccountMfaPreferredMethod::PASSKEY, $now));

        $worker = dirname(__DIR__, 2) . '/Support/MySql/P2MultiFactorMutationWorker.php';
        $payloads = [
            json_encode(['operation' => 'revoke_factor', 'account_id' => $this->accountId,
                'factor_type' => 'totp', 'public_id' => $pending->publicId,
                'now' => $now->format(DATE_ATOM)], JSON_THROW_ON_ERROR),
            json_encode(['operation' => 'revoke_factor', 'account_id' => $this->accountId,
                'factor_type' => 'passkey', 'public_id' => $passkey->publicId,
                'now' => $now->format(DATE_ATOM)], JSON_THROW_ON_ERROR),
        ];
        $results = $this->runWorkerPayloads($worker, $payloads);
        sort($results);
        self::assertSame([false, true], $results);
        self::assertSame(1, $this->repository->activeStrongFactorCount($this->accountId));
    }

    /** @return list<bool> */
    private function runWorkers(string $payload, int $count): array
    {
        $worker = dirname(__DIR__, 2) . '/Support/MySql/P2MultiFactorMutationWorker.php';
        return $this->runWorkerPayloads($worker, array_fill(0, $count, $payload));
    }

    /** @param list<string> $payloads
     *  @return list<bool>
     */
    private function runWorkerPayloads(string $worker, array $payloads): array
    {
        $processes = [];
        foreach ($payloads as $payload) {
            $pipes = [];
            $process = proc_open(
                [PHP_BINARY, $worker],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                dirname(__DIR__, 3),
                null,
                ['bypass_shell' => true],
            );
            self::assertIsResource($process);
            fwrite($pipes[0], $payload);
            fclose($pipes[0]);
            $processes[] = [$process, $pipes];
        }
        $results = [];
        foreach ($processes as [$process, $pipes]) {
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(0, proc_close($process), is_string($stderr) ? $stderr : '');
            $decoded = json_decode(is_string($stdout) ? $stdout : '', true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($decoded);
            self::assertIsBool($decoded['mutated'] ?? null);
            $results[] = $decoded['mutated'];
        }

        return $results;
    }

    private function seedSession(DateTimeImmutable $now): int
    {
        $formatted = $now->format('Y-m-d H:i:s.u');
        $device = $this->connection->prepare(
            "INSERT INTO user_devices (public_id, account_id, token_hash, status, version, created_at, "
            . "last_seen_at, updated_at) VALUES (:public_id, :account_id, :token_hash, 'ACTIVE', 1, "
            . ':created_at, :last_seen_at, :updated_at)',
        );
        $device->bindValue(':public_id', UuidV7::generate()->toBinary(), PDO::PARAM_LOB);
        $device->bindValue(':account_id', $this->accountId, PDO::PARAM_INT);
        $device->bindValue(':token_hash', random_bytes(32), PDO::PARAM_LOB);
        $device->bindValue(':created_at', $formatted);
        $device->bindValue(':last_seen_at', $formatted);
        $device->bindValue(':updated_at', $formatted);
        $device->execute();
        $deviceId = (int)$this->connection->lastInsertId();
        $session = $this->connection->prepare(
            "INSERT INTO user_sessions (public_id, account_id, device_id, login_submission_id, "
            . "current_token_hash, status, version, issued_at, authenticated_at, primary_authentication_method, "
            . "secondary_authentication_method, assurance_level, strong_authenticated_at, last_seen_at, "
            . "idle_expires_at, absolute_expires_at, rotated_at, updated_at) VALUES "
            . "(:public_id, :account_id, :device_id, :login_submission_id, :current_token_hash, 'ACTIVE', 1, "
            . ":issued_at, :authenticated_at, 'PASSWORD', NULL, 'PRIMARY', NULL, :last_seen_at, "
            . ':idle_expires_at, :absolute_expires_at, :rotated_at, :updated_at)',
        );
        foreach (['public_id', 'login_submission_id'] as $parameter) {
            $session->bindValue(':' . $parameter, UuidV7::generate()->toBinary(), PDO::PARAM_LOB);
        }
        $session->bindValue(':current_token_hash', random_bytes(32), PDO::PARAM_LOB);
        $session->bindValue(':account_id', $this->accountId, PDO::PARAM_INT);
        $session->bindValue(':device_id', $deviceId, PDO::PARAM_INT);
        foreach (['issued_at', 'authenticated_at', 'last_seen_at', 'rotated_at', 'updated_at'] as $parameter) {
            $session->bindValue(':' . $parameter, $formatted);
        }
        $session->bindValue(':idle_expires_at', $now->modify('+30 minutes')->format('Y-m-d H:i:s.u'));
        $session->bindValue(':absolute_expires_at', $now->modify('+8 hours')->format('Y-m-d H:i:s.u'));
        $session->execute();

        return (int)$this->connection->lastInsertId();
    }

    private function seedAccount(): int
    {
        $now = '2026-08-27 12:00:00.000000';
        $statement = $this->connection->prepare(
            "INSERT INTO user_accounts (public_id, account_status, preferred_locale, preferred_time_zone, "
            . "version, created_at, updated_at) VALUES "
            . "(:public_id, 'ACTIVE', 'en', 'UTC', 1, :created_at, :updated_at)",
        );
        $statement->bindValue(':public_id', AccountId::generate()->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':created_at', $now);
        $statement->bindValue(':updated_at', $now);
        $statement->execute();

        return (int)$this->connection->lastInsertId();
    }

    private function clearRows(): void
    {
        foreach (
            [
                'workspace_role_assignments', 'platform_role_assignments',
                'account_webauthn_ceremonies', 'account_passkey_credentials', 'account_webauthn_user_handles',
                'account_recovery_codes', 'account_recovery_code_sets', 'account_totp_authenticators',
                'account_step_up_grants', 'account_authentication_transactions', 'account_mfa_policies',
                'user_sessions', 'user_devices', 'account_security_notification_events',
                'account_security_notifications', 'account_password_recovery_events',
                'account_password_recovery_challenges', 'account_email_verification_challenges',
                'identity_rate_limit_buckets', 'identity_idempotency_records', 'workspace_memberships',
                'account_status_events', 'account_credentials', 'account_phone_numbers',
                'account_email_addresses', 'user_accounts', 'workspaces',
            ] as $table
        ) {
            $this->connection->exec('DELETE FROM ' . $table);
        }
    }

    private function rebuildSchema(): void
    {
        foreach (
            [
                'people_profile_operation_results', 'people_guardianships', 'people_memorizer_progress',
                'people_role_profiles', 'people_person_geographies', 'people_account_links', 'people_person_names',
                'people_persons',
                'geography_administrative_areas', 'geography_dataset_versions', 'geography_countries',
                'account_state_operations', 'security_audit_checkpoint_heads', 'security_audit_checkpoints',
                'security_audit_events', 'security_audit_streams',
                'privileged_access_reviews', 'privileged_access_events', 'privileged_access_activations',
            'privileged_access_approvals', 'privileged_access_request_permissions',
            'privileged_access_requests', 'privileged_access_permission_policies',
            'workspace_role_assignments', 'platform_role_assignments', 'authorization_role_permissions',
            'authorization_roles', 'authorization_permissions', 'account_webauthn_ceremonies',
            'account_passkey_credentials', 'account_webauthn_user_handles',
            'account_recovery_codes', 'account_recovery_code_sets', 'account_totp_authenticators',
            'account_step_up_grants', 'account_authentication_transactions', 'account_mfa_policies',
            'account_security_notification_events', 'account_security_notifications',
            'account_password_recovery_events', 'account_password_recovery_challenges',
            'user_sessions', 'user_devices', 'account_email_verification_challenges',
            'identity_rate_limit_buckets', 'identity_idempotency_records', 'workspace_memberships',
            'account_status_events', 'account_credentials', 'account_phone_numbers',
            'account_email_addresses', 'user_accounts', 'workspaces', 'qmdb_scheduled_task_runs',
            ] as $table
        ) {
            $this->connection->exec('DROP TABLE IF EXISTS ' . $table);
        }
        $factory = require dirname(__DIR__, 3) . '/database/migrations.php';
        self::assertIsCallable($factory);
        $registry = $factory();
        self::assertInstanceOf(MigrationRegistry::class, $registry);
        foreach ($registry->ordered() as $migration) {
            foreach ($migration->up() as $step) {
                $this->connection->prepare($step->sql())->execute($step->parameters());
            }
        }
    }
}
