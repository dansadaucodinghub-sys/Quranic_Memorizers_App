<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\MySql;

use DateTimeImmutable;
use DateTimeZone;
use Nyholm\Psr7\ServerRequest;
use PDO;
use PDOException;
use Psr\Http\Message\ResponseInterface;
use Qmdb\Shared\Schema\Migration\MigrationRegistry;
use Qmdb\Bootstrap\ApplicationFactory;
use Qmdb\Bootstrap\Http\HttpRuntime;
use Qmdb\Bootstrap\Shared\ExitCode;
use Qmdb\Modules\Identity\Domain\Value\AccountEmailId;
use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\Identity\Domain\Value\CredentialId;
use Qmdb\Modules\Identity\Infrastructure\Migration\CreateAccountSecurityFoundationMigration;
use Qmdb\Modules\Identity\Infrastructure\Migration\CreateUserAccountsMigration;
use Qmdb\Modules\Identity\Infrastructure\Security\SodiumContactCipher;
use Qmdb\Modules\IdentityAccess\Infrastructure\Migration\CreateIdentityRateLimitFoundationMigration;
use Qmdb\Modules\IdentityAccess\Infrastructure\Migration\CreateIdentityVerificationFoundationMigration;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\EmailLookupHashGenerator;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryChallengeId;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryTokenHash;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordResetSubmissionId;
use Qmdb\Modules\IdentityRecovery\Infrastructure\Migration\CreatePasswordRecoveryFoundationMigration;
use Qmdb\Modules\IdentityRecovery\Infrastructure\Migration\ExtendIdentityRecoveryConstraintsMigration;
use Qmdb\Modules\IdentityRecovery\Infrastructure\Persistence\MySqlPasswordRecoveryRepository;
use Qmdb\Modules\IdentityRecovery\Infrastructure\Security\SecurePasswordRecoveryTokenGenerator;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationId;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\NotificationClaimExecutionId;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\SecurityNotificationDeduplicationKeyFactory;
use Qmdb\Modules\IdentitySecurityNotifications\Infrastructure\Migration\CreateSecurityNotificationFoundationMigration;
use Qmdb\Modules\IdentitySecurityNotifications\Infrastructure\Persistence\MySqlAccountSecurityNotificationRepository;
use Qmdb\Modules\IdentitySessions\Infrastructure\Migration\CreateUserDevicesMigration;
use Qmdb\Modules\IdentitySessions\Infrastructure\Migration\CreateUserSessionsMigration;
use Qmdb\Modules\IdentityMultiFactor\Infrastructure\Migration\CreateAuthenticationTransactionFoundationMigration;
use Qmdb\Modules\IdentityMultiFactor\Infrastructure\Migration\CreatePasskeyFoundationMigration;
use Qmdb\Modules\IdentityMultiFactor\Infrastructure\Migration\CreateTotpRecoveryCodeFoundationMigration;
use Qmdb\Modules\IdentityMultiFactor\Infrastructure\Migration\ExtendIdentityMultiFactorConstraintsMigration;
use Qmdb\Modules\Tenancy\Infrastructure\Migration\CreateWorkspaceMembershipsMigration;
use Qmdb\Modules\Tenancy\Infrastructure\Migration\CreateWorkspacesMigration;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Connection\MySqlConnectionProvider;
use Qmdb\Shared\Background\Scheduler\Migration\CreateScheduledTaskRunsMigration;
use Qmdb\Shared\Presentation\Response\FragmentRequestDetector;
use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Tests\Support\MySql\MySqlIntegrationTestCase;

final class P2IdentityRecoveryHttpIntegrationTest extends MySqlIntegrationTestCase
{
    private const PASSWORD = 'Strong passphrase 123!';
    private const NEW_PASSWORD = 'New secure passphrase 456!';
    private const HMAC_KEY = 'qmdb-test-identity-hmac-key-32-bytes-minimum';
    private const CONTACT_KEY = 'cccccccccccccccccccccccccccccccc';

    private PDO $connection;
    private MySqlConnectionProvider $connectionProvider;
    private HttpRuntime $runtime;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connectionProvider = $this->provider();
        $this->connection = $this->connectionProvider->connection();
        $this->rebuildIdentityTables();
        $this->runtime = ApplicationFactory::fromCurrentProcess()->createHttpRuntime();
    }

    protected function tearDown(): void
    {
        $this->clearIdentityRows();
        parent::tearDown();
    }

    public function testRecoveryMigrationsAndRepositoryEnforceHashAndOwnershipConstraints(): void
    {
        $readiness = $this->runtime->handle(new ServerRequest('GET', '/health/ready'));
        self::assertSame(200, $readiness->getStatusCode());
        self::assertSame('{"status":"ready"}', (string)$readiness->getBody());

        self::assertCount(16, $this->migrationRegistry()->ordered());
        foreach (
            ['account_password_recovery_challenges', 'account_password_recovery_events',
                'account_security_notifications', 'account_security_notification_events'] as $table
        ) {
            self::assertSame('InnoDB', $this->scalar(
                "SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() "
                . "AND TABLE_NAME = '$table'",
            ));
        }
        self::assertSame('binary', strtolower((string)$this->scalar(
            "SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() "
            . "AND TABLE_NAME = 'account_password_recovery_challenges' AND COLUMN_NAME = 'token_hash'",
        )));
        self::assertSame(32, (int)$this->scalar(
            "SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() "
            . "AND TABLE_NAME = 'account_security_notifications' AND COLUMN_NAME = 'deduplication_key'",
        ));

        $email = 'recovery-' . bin2hex(random_bytes(5)) . '@example.test';
        [$accountInternalId] = $this->seedActiveAccount($email);
        $repository = new MySqlPasswordRecoveryRepository($this->connectionProvider);
        $target = $repository->targetByEmailHash((new EmailLookupHashGenerator(self::HMAC_KEY))->generate($email));
        self::assertNotNull($target);
        $token = (new SecurePasswordRecoveryTokenGenerator())->generate();
        $challengeId = PasswordRecoveryChallengeId::generate();
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $repository->createPending(
            $target,
            $challengeId,
            PasswordRecoveryTokenHash::fromToken($token),
            'en',
            5,
            $now->modify('+30 minutes'),
            $now,
        );

        self::assertSame(1, $this->countRows('account_password_recovery_challenges'));
        self::assertSame(
            strtoupper(bin2hex(hash('sha256', $token->revealForProof(), true))),
            $this->scalar('SELECT HEX(token_hash) FROM account_password_recovery_challenges'),
        );
        self::assertStringNotContainsString(
            $token->revealForProof(),
            (string)$this->scalar('SELECT HEX(token_hash) FROM account_password_recovery_challenges'),
        );

        $second = (new SecurePasswordRecoveryTokenGenerator())->generate();
        $this->expectException(PDOException::class);
        $repository->createPending(
            $target,
            PasswordRecoveryChallengeId::generate(),
            PasswordRecoveryTokenHash::fromToken($second),
            'en',
            5,
            $now->modify('+30 minutes'),
            $now,
        );
        self::assertGreaterThan(0, $accountInternalId);
    }

    public function testForgotPasswordIsAccessibleGenericAndProgressivelyEnhanced(): void
    {
        $email = 'forgot-' . bin2hex(random_bytes(5)) . '@example.test';
        $this->seedActiveAccount($email);
        $english = $this->runtime->handle(new ServerRequest('GET', '/forgot-password'));
        self::assertSame(200, $english->getStatusCode());
        self::assertStringContainsString('<html lang="en" dir="ltr"', (string)$english->getBody());
        self::assertSame(1, substr_count((string)$english->getBody(), '<h1'));
        self::assertStringContainsString('<label for="recovery-email">', (string)$english->getBody());
        self::assertStringNotContainsString('data-qmdb-modal-url', (string)$english->getBody());

        $arabic = $this->runtime->handle(new ServerRequest('GET', '/forgot-password?lang=ar'));
        self::assertStringContainsString('<html lang="ar" dir="rtl"', (string)$arabic->getBody());

        [$csrf, $submission, $cookies] = $this->recoveryForm();
        $accepted = $this->runtime->handle($this->post('/forgot-password', [
            'email' => $email,
            'csrf_token' => $csrf,
            'recovery_request_submission_id' => $submission,
        ], $cookies));
        self::assertSame(303, $accepted->getStatusCode());
        self::assertSame('/forgot-password/accepted', $accepted->getHeaderLine('Location'));
        self::assertSame(1, $this->countRows('account_password_recovery_challenges'));

        [$unknownCsrf, $unknownSubmission, $unknownCookies] = $this->recoveryForm();
        $unknown = $this->runtime->handle($this->post('/forgot-password', [
            'email' => 'unknown-' . bin2hex(random_bytes(4)) . '@example.test',
            'csrf_token' => $unknownCsrf,
            'recovery_request_submission_id' => $unknownSubmission,
        ], $unknownCookies));
        self::assertSame($accepted->getStatusCode(), $unknown->getStatusCode());
        self::assertSame($accepted->getHeaderLine('Location'), $unknown->getHeaderLine('Location'));

        [$enhancedCsrf, $enhancedSubmission, $enhancedCookies] = $this->recoveryForm();
        $enhanced = $this->runtime->handle($this->post('/forgot-password', [
            'email' => 'enhanced-' . bin2hex(random_bytes(4)) . '@example.test',
            'csrf_token' => $enhancedCsrf,
            'recovery_request_submission_id' => $enhancedSubmission,
        ], $enhancedCookies, [
            'Accept' => FragmentRequestDetector::MEDIA_TYPE,
            'X-QMDB-CSRF' => $enhancedCsrf,
            'Idempotency-Key' => $enhancedSubmission,
        ]));
        self::assertSame(202, $enhanced->getStatusCode());
        self::assertStringNotContainsString('challenge', strtolower((string)$enhanced->getBody()));
        self::assertNotSame('', $enhanced->getHeaderLine('X-Request-ID'));

        $invalid = $this->runtime->handle($this->post('/forgot-password', [
            'email' => 'invalid',
            'csrf_token' => $csrf,
            'recovery_request_submission_id' => $submission,
        ], $cookies));
        self::assertSame(422, $invalid->getStatusCode());
        self::assertSame('no-store', $invalid->getHeaderLine('Cache-Control'));
    }

    public function testValidResetIsAtomicRevokesSessionsAndDeliversOneNotification(): void
    {
        $email = 'reset-' . bin2hex(random_bytes(5)) . '@example.test';
        [$accountInternalId] = $this->seedActiveAccount($email);
        $loginCookies = $this->login($email);
        self::assertSame(1, $this->countRows('user_sessions'));
        self::assertSame(1, $this->countRows('user_devices'));

        $repository = new MySqlPasswordRecoveryRepository($this->connectionProvider);
        $target = $repository->targetByEmailHash((new EmailLookupHashGenerator(self::HMAC_KEY))->generate($email));
        self::assertNotNull($target);
        $token = (new SecurePasswordRecoveryTokenGenerator())->generate();
        $challengeId = PasswordRecoveryChallengeId::generate();
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $repository->createPending(
            $target,
            $challengeId,
            PasswordRecoveryTokenHash::fromToken($token),
            'en',
            5,
            $now->modify('+30 minutes'),
            $now,
        );

        $path = '/reset-password/' . $challengeId->toString();
        $get = $this->runtime->handle((new ServerRequest(
            'GET',
            $path . '?token=' . rawurlencode($token->revealForProof()),
        ))->withCookieParams($loginCookies));
        self::assertSame(200, $get->getStatusCode());
        self::assertSame('no-store', $get->getHeaderLine('Cache-Control'));
        self::assertSame('no-referrer', $get->getHeaderLine('Referrer-Policy'));
        self::assertSame('noindex, nofollow', $get->getHeaderLine('X-Robots-Tag'));
        self::assertSame(0, (int)$this->scalar('SELECT attempt_count FROM account_password_recovery_challenges'));
        $body = (string)$get->getBody();
        $csrf = $this->hidden($body, 'csrf_token');
        $submission = $this->hidden($body, 'password_reset_submission_id');
        $resetCookies = array_replace($loginCookies, $this->cookies($get));

        $response = $this->runtime->handle($this->post($path, [
            'token' => $token->revealForProof(),
            'new_password' => self::NEW_PASSWORD,
            'new_password_confirmation' => self::NEW_PASSWORD,
            'csrf_token' => $csrf,
            'password_reset_submission_id' => $submission,
        ], $resetCookies));
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/reset-password/completed', $response->getHeaderLine('Location'));
        self::assertStringContainsString('qmdb_session=;', implode("\n", $response->getHeader('Set-Cookie')));
        self::assertStringNotContainsString('qmdb_device=;', implode("\n", $response->getHeader('Set-Cookie')));
        self::assertSame('CONSUMED', $this->scalar('SELECT status FROM account_password_recovery_challenges'));
        self::assertSame(1, (int)$this->scalar(
            "SELECT COUNT(*) FROM account_credentials WHERE credential_status = 'ACTIVE'",
        ));
        self::assertSame(1, (int)$this->scalar(
            "SELECT COUNT(*) FROM account_credentials WHERE credential_status = 'REVOKED'",
        ));
        $newHash = (string)$this->scalar(
            "SELECT password_hash FROM account_credentials WHERE credential_status = 'ACTIVE'",
        );
        self::assertTrue(password_verify(self::NEW_PASSWORD, $newHash));
        self::assertSame('PASSWORD_RESET', $this->scalar('SELECT revoke_reason_code FROM user_sessions'));
        self::assertSame('ACTIVE', $this->scalar('SELECT status FROM user_devices'));
        self::assertSame('ACTIVE', $this->scalar('SELECT account_status FROM user_accounts'));
        self::assertSame(1, $this->countRows('account_security_notifications'));
        self::assertSame(1, (int)$this->scalar(
            "SELECT COUNT(*) FROM account_password_recovery_events WHERE event_type = 'COMPLETED'",
        ));
        self::assertSame(
            $accountInternalId,
            (int)$this->scalar('SELECT account_id FROM account_security_notifications'),
        );

        $this->connection->exec(
            "DELETE FROM qmdb_scheduled_task_runs WHERE task_id = 'identity.security_notifications.deliver'",
        );
        $schedule = ApplicationFactory::fromCurrentProcess()->createConsoleApplication()->run(['schedule:run']);
        self::assertSame(
            ExitCode::SUCCESS,
            $schedule->exitCode(),
            $schedule->standardOutput() . $schedule->standardError(),
        );
        self::assertStringContainsString('Succeeded: 1', $schedule->standardOutput());
        self::assertSame('DELIVERED', $this->scalar('SELECT status FROM account_security_notifications'));

        $repeat = ApplicationFactory::fromCurrentProcess()->createConsoleApplication()->run(['schedule:run']);
        self::assertSame(ExitCode::SUCCESS, $repeat->exitCode());
        self::assertSame(1, (int)$this->scalar(
            "SELECT COUNT(*) FROM account_security_notification_events WHERE event_type = 'DELIVERED'",
        ));
    }

    public function testNotificationClaimsAreLeasedDeduplicatedAndCompareAndSwapProtected(): void
    {
        [$accountInternalId, $emailInternalId, $accountId] = $this->seedActiveAccount(
            'notification-' . bin2hex(random_bytes(5)) . '@example.test',
        );
        $repository = new MySqlAccountSecurityNotificationRepository($this->connectionProvider);
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $deduplication = (new SecurityNotificationDeduplicationKeyFactory())->passwordResetCompleted(
            PasswordRecoveryChallengeId::generate()->toString(),
            $accountId->toString(),
        );
        $repository->createPendingIntent(
            AccountSecurityNotificationId::generate(),
            $accountInternalId,
            $emailInternalId,
            AccountSecurityNotificationType::PASSWORD_RESET_COMPLETED,
            $deduplication,
            'ar',
            5,
            $now,
        );

        try {
            $repository->createPendingIntent(
                AccountSecurityNotificationId::generate(),
                $accountInternalId,
                $emailInternalId,
                AccountSecurityNotificationType::PASSWORD_RESET_COMPLETED,
                $deduplication,
                'ar',
                5,
                $now,
            );
            self::fail('Duplicate notification intent must be rejected.');
        } catch (PDOException) {
            self::assertSame(1, $this->countRows('account_security_notifications'));
        }

        $firstExecution = NotificationClaimExecutionId::generate();
        $claimed = $this->transactionManager($this->connectionProvider)->transactional(
            fn (): array => $repository->claimDue($firstExecution, $now, $now->modify('+2 minutes'), 25),
        );
        self::assertCount(1, $claimed);
        self::assertSame(1, $claimed[0]->attemptCount);
        self::assertFalse($repository->markDelivered(
            $claimed[0],
            NotificationClaimExecutionId::generate(),
            $now,
        ));
        self::assertTrue($repository->scheduleRetry(
            $claimed[0],
            $firstExecution,
            $now->modify('+1 minute'),
            'MAIL_TRANSPORT',
            $now,
        ));
        self::assertSame('PENDING', $this->scalar('SELECT status FROM account_security_notifications'));

        $secondExecution = NotificationClaimExecutionId::generate();
        $reclaimed = $this->transactionManager($this->connectionProvider)->transactional(
            fn (): array => $repository->claimDue(
                $secondExecution,
                $now->modify('+2 minutes'),
                $now->modify('+4 minutes'),
                25,
            ),
        );
        self::assertCount(1, $reclaimed);
        self::assertSame(2, $reclaimed[0]->attemptCount);
        self::assertTrue($repository->markDelivered($reclaimed[0], $secondExecution, $now->modify('+2 minutes')));
        self::assertSame('DELIVERED', $this->scalar('SELECT status FROM account_security_notifications'));
        self::assertGreaterThanOrEqual(5, $this->countRows('account_security_notification_events'));
    }

    public function testConcurrentResetConsumesOnceAndCreatesExactlyOneReplacementCredential(): void
    {
        $email = 'concurrent-reset-' . bin2hex(random_bytes(5)) . '@example.test';
        $this->seedActiveAccount($email);
        $repository = new MySqlPasswordRecoveryRepository($this->connectionProvider);
        $target = $repository->targetByEmailHash((new EmailLookupHashGenerator(self::HMAC_KEY))->generate($email));
        self::assertNotNull($target);
        $token = (new SecurePasswordRecoveryTokenGenerator())->generate();
        $challengeId = PasswordRecoveryChallengeId::generate();
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $repository->createPending(
            $target,
            $challengeId,
            PasswordRecoveryTokenHash::fromToken($token),
            'en',
            5,
            $now->modify('+30 minutes'),
            $now,
        );

        $path = '/reset-password/' . $challengeId->toString();
        $form = $this->runtime->handle(new ServerRequest(
            'GET',
            $path . '?token=' . rawurlencode($token->revealForProof()),
        ));
        $body = (string)$form->getBody();
        $csrf = $this->hidden($body, 'csrf_token');
        $cookies = $this->cookies($form);
        $fields = [
            'token' => $token->revealForProof(),
            'new_password' => self::NEW_PASSWORD,
            'new_password_confirmation' => self::NEW_PASSWORD,
            'csrf_token' => $csrf,
        ];
        $payload = [
            'path' => $path,
            'fields' => $fields + [
                'password_reset_submission_id' => PasswordResetSubmissionId::generate()->toString(),
            ],
            'cookies' => $cookies,
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'peer' => '192.0.2.90',
        ];
        $other = $payload;
        $other['fields'] = $fields + [
            'password_reset_submission_id' => PasswordResetSubmissionId::generate()->toString(),
        ];

        $results = $this->runConcurrentMutations([$payload, $other]);
        self::assertSame([303, 422], array_column($results, 'status'));
        self::assertSame(1, (int)$this->scalar(
            "SELECT COUNT(*) FROM account_credentials WHERE credential_status = 'ACTIVE'",
        ));
        self::assertSame(1, (int)$this->scalar(
            "SELECT COUNT(*) FROM account_credentials WHERE credential_status = 'REVOKED'",
        ));
        self::assertSame(1, (int)$this->scalar(
            "SELECT COUNT(*) FROM account_password_recovery_events WHERE event_type = 'COMPLETED'",
        ));
        self::assertSame(1, $this->countRows('account_security_notifications'));
    }

    /** @return array{int, int, AccountId} */
    private function seedActiveAccount(string $email): array
    {
        $accountId = AccountId::generate();
        $account = $this->connection->prepare(
            "INSERT INTO user_accounts (public_id, account_status, preferred_locale, preferred_time_zone, "
            . "version, created_at, updated_at) VALUES "
            . "(:public_id, 'ACTIVE', 'en', 'UTC', 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))",
        );
        $account->bindValue(':public_id', $accountId->toBinary(), PDO::PARAM_LOB);
        $account->execute();
        $accountInternalId = (int)$this->connection->lastInsertId();

        $cipher = new SodiumContactCipher(self::CONTACT_KEY, 'test-v1');
        $emailStatement = $this->connection->prepare(
            "INSERT INTO account_email_addresses (public_id, user_account_id, email_ciphertext, encryption_key_id, "
            . "lookup_hash, status_code, verified_at, version, created_at, updated_at) VALUES "
            . "(:public_id, :account_id, :ciphertext, 'test-v1', :lookup_hash, 'VERIFIED', "
            . 'UTC_TIMESTAMP(6), 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))',
        );
        $emailStatement->bindValue(':public_id', AccountEmailId::generate()->toBinary(), PDO::PARAM_LOB);
        $emailStatement->bindValue(':account_id', $accountInternalId, PDO::PARAM_INT);
        $emailStatement->bindValue(':ciphertext', $cipher->encrypt($email), PDO::PARAM_LOB);
        $emailStatement->bindValue(
            ':lookup_hash',
            (new EmailLookupHashGenerator(self::HMAC_KEY))->generate($email)->toBinary(),
            PDO::PARAM_LOB,
        );
        $emailStatement->execute();
        $emailInternalId = (int)$this->connection->lastInsertId();

        $credential = $this->connection->prepare(
            "INSERT INTO account_credentials (public_id, user_account_id, credential_type, credential_status, "
            . "password_hash, algorithm, metadata_version, version, created_at, updated_at) VALUES "
            . "(:public_id, :account_id, 'PASSWORD', 'ACTIVE', :password_hash, 'argon2id', 1, 1, "
            . 'UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))',
        );
        $credential->bindValue(':public_id', CredentialId::generate()->toBinary(), PDO::PARAM_LOB);
        $credential->bindValue(':account_id', $accountInternalId, PDO::PARAM_INT);
        $credential->bindValue(':password_hash', password_hash(self::PASSWORD, PASSWORD_ARGON2ID));
        $credential->execute();

        return [$accountInternalId, $emailInternalId, $accountId];
    }

    /** @return array{string, string, array<string, string>} */
    private function recoveryForm(): array
    {
        $response = $this->runtime->handle(new ServerRequest('GET', '/forgot-password'));
        $body = (string)$response->getBody();

        return [
            $this->hidden($body, 'csrf_token'),
            $this->hidden($body, 'recovery_request_submission_id'),
            $this->cookies($response),
        ];
    }

    /** @return array<string, string> */
    private function login(string $email): array
    {
        $form = $this->runtime->handle(new ServerRequest('GET', '/login'));
        $body = (string)$form->getBody();
        $cookies = $this->cookies($form);
        $response = $this->runtime->handle($this->post('/login', [
            'email' => $email,
            'password' => self::PASSWORD,
            'csrf_token' => $this->hidden($body, 'csrf_token'),
            'login_submission_id' => $this->hidden($body, 'login_submission_id'),
            'return_to' => '/account/security/sessions',
        ], $cookies));
        self::assertSame(303, $response->getStatusCode());

        return array_replace($cookies, $this->cookies($response));
    }

    private function hidden(string $body, string $name): string
    {
        self::assertSame(1, preg_match(
            '/name="' . preg_quote($name, '/') . '" value="([^"]+)"/',
            $body,
            $matches,
        ));

        $value = $matches[1] ?? null;
        self::assertIsString($value);

        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function migrationRegistry(): MigrationRegistry
    {
        $factory = require dirname(__DIR__, 3) . '/database/migrations.php';
        self::assertIsCallable($factory);
        $registry = $factory();
        self::assertInstanceOf(MigrationRegistry::class, $registry);

        return $registry;
    }

    /** @return array<string, string> */
    private function cookies(ResponseInterface $response): array
    {
        $cookies = [];
        foreach ($response->getHeader('Set-Cookie') as $header) {
            [$pair] = explode(';', $header, 2);
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $cookies[$name] = $value;
        }

        return $cookies;
    }

    /**
     * @param array<string, string> $fields
     * @param array<string, string> $cookies
     * @param array<string, string> $headers
     */
    private function post(string $path, array $fields, array $cookies, array $headers = []): ServerRequest
    {
        return (new ServerRequest(
            'POST',
            $path,
            array_replace(['Content-Type' => 'application/x-www-form-urlencoded'], $headers),
            http_build_query($fields, '', '&', PHP_QUERY_RFC3986),
            '1.1',
            ['REMOTE_ADDR' => '192.0.2.77'],
        ))->withCookieParams($cookies);
    }

    /**
     * @param list<array<string, mixed>> $payloads
     * @return list<array{status: int, location: string}>
     */
    private function runConcurrentMutations(array $payloads): array
    {
        $processes = [];
        $worker = dirname(__DIR__, 2) . '/Support/Http/P2MutationWorker.php';
        foreach ($payloads as $payload) {
            $pipes = [];
            $process = proc_open(
                [PHP_BINARY, $worker],
                [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ],
                $pipes,
                dirname(__DIR__, 3),
                null,
                ['bypass_shell' => true],
            );
            self::assertIsResource($process);
            if (!is_resource($process)) {
                self::fail('Concurrent mutation worker could not start.');
            }
            fwrite($pipes[0], json_encode($payload, JSON_THROW_ON_ERROR));
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
            $status = $decoded['status'] ?? null;
            $location = $decoded['location'] ?? null;
            self::assertIsInt($status);
            self::assertIsString($location);
            $results[] = ['status' => $status, 'location' => $location];
        }
        usort($results, static fn (array $left, array $right): int => $left['status'] <=> $right['status']);

        return $results;
    }

    private function rebuildIdentityTables(): void
    {
        $this->connection->exec('DROP TABLE IF EXISTS qmdb_scheduled_task_runs');
        foreach ($this->identityTables() as $table) {
            $this->connection->exec('DROP TABLE IF EXISTS ' . $table);
        }
        foreach ($this->migrations() as $migration) {
            foreach ($migration->up() as $step) {
                $this->connection->prepare($step->sql())->execute($step->parameters());
            }
        }
    }

    private function clearIdentityRows(): void
    {
        foreach ($this->identityTables() as $table) {
            if ($this->tableExists($table)) {
                $this->connection->exec('DELETE FROM ' . $table);
            }
        }
    }

    /** @return list<string> */
    private function identityTables(): array
    {
        return [
            'account_webauthn_ceremonies',
            'account_passkey_credentials',
            'account_webauthn_user_handles',
            'account_recovery_codes',
            'account_recovery_code_sets',
            'account_totp_authenticators',
            'account_step_up_grants',
            'account_authentication_transactions',
            'account_mfa_policies',
            'account_security_notification_events',
            'account_security_notifications',
            'account_password_recovery_events',
            'account_password_recovery_challenges',
            'user_sessions',
            'user_devices',
            'account_email_verification_challenges',
            'identity_rate_limit_buckets',
            'identity_idempotency_records',
            'workspace_memberships',
            'account_status_events',
            'account_credentials',
            'account_phone_numbers',
            'account_email_addresses',
            'user_accounts',
            'workspaces',
        ];
    }

    /** @return list<Migration> */
    private function migrations(): array
    {
        return [
            new CreateScheduledTaskRunsMigration(),
            new CreateWorkspacesMigration(),
            new CreateUserAccountsMigration(),
            new CreateAccountSecurityFoundationMigration(),
            new CreateWorkspaceMembershipsMigration(),
            new CreateIdentityVerificationFoundationMigration(),
            new CreateIdentityRateLimitFoundationMigration(),
            new CreateUserDevicesMigration(),
            new CreateUserSessionsMigration(),
            new ExtendIdentityRecoveryConstraintsMigration(),
            new CreatePasswordRecoveryFoundationMigration(),
            new CreateSecurityNotificationFoundationMigration(),
            new ExtendIdentityMultiFactorConstraintsMigration(),
            new CreateAuthenticationTransactionFoundationMigration(),
            new CreateTotpRecoveryCodeFoundationMigration(),
            new CreatePasskeyFoundationMigration(),
        ];
    }

    private function tableExists(string $table): bool
    {
        return (int)$this->scalar(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() "
            . "AND TABLE_NAME = '$table'",
        ) === 1;
    }

    private function countRows(string $table): int
    {
        return (int)$this->scalar('SELECT COUNT(*) FROM ' . $table);
    }

    private function scalar(string $sql): int|string
    {
        $statement = $this->connection->query($sql);
        self::assertInstanceOf(\PDOStatement::class, $statement);
        $value = $statement->fetchColumn();
        if (!is_int($value) && !is_string($value)) {
            self::fail('Expected scalar MySQL value.');
        }

        return $value;
    }
}
