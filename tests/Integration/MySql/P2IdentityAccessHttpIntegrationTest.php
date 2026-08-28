<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\MySql;

use DateTimeImmutable;
use Nyholm\Psr7\ServerRequest;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Qmdb\Bootstrap\ApplicationFactory;
use Qmdb\Bootstrap\Http\HttpRuntime;
use Qmdb\Modules\Identity\Domain\AccountContactStatus;
use Qmdb\Modules\Identity\Domain\AccountStatus;
use Qmdb\Modules\Identity\Domain\UserAccount;
use Qmdb\Modules\Identity\Domain\Value\AccountEmailId;
use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\Identity\Domain\Value\LookupHash;
use Qmdb\Modules\Identity\Infrastructure\Migration\CreateAccountSecurityFoundationMigration;
use Qmdb\Modules\Identity\Infrastructure\Migration\CreateUserAccountsMigration;
use Qmdb\Modules\Identity\Infrastructure\Persistence\MySqlAccountRepository;
use Qmdb\Modules\Identity\Infrastructure\Security\SodiumContactCipher;
use Qmdb\Modules\IdentityAccess\Domain\EmailVerificationChallengeId;
use Qmdb\Modules\IdentityAccess\Domain\EmailVerificationTokenHash;
use Qmdb\Modules\IdentityAccess\Infrastructure\Migration\CreateIdentityRateLimitFoundationMigration;
use Qmdb\Modules\IdentityAccess\Infrastructure\Migration\CreateIdentityVerificationFoundationMigration;
use Qmdb\Modules\IdentityRecovery\Infrastructure\Migration\CreatePasswordRecoveryFoundationMigration;
use Qmdb\Modules\IdentityRecovery\Infrastructure\Migration\ExtendIdentityRecoveryConstraintsMigration;
use Qmdb\Modules\IdentitySecurityNotifications\Infrastructure\Migration\CreateSecurityNotificationFoundationMigration;
use Qmdb\Modules\IdentitySessions\Infrastructure\Migration\CreateUserDevicesMigration;
use Qmdb\Modules\IdentitySessions\Infrastructure\Migration\CreateUserSessionsMigration;
use Qmdb\Modules\IdentityMultiFactor\Infrastructure\Migration\CreateAuthenticationTransactionFoundationMigration;
use Qmdb\Modules\IdentityMultiFactor\Infrastructure\Migration\CreatePasskeyFoundationMigration;
use Qmdb\Modules\IdentityMultiFactor\Infrastructure\Migration\CreateTotpRecoveryCodeFoundationMigration;
use Qmdb\Modules\IdentityMultiFactor\Infrastructure\Migration\ExtendIdentityMultiFactorConstraintsMigration;
use Qmdb\Modules\IdentityAccess\Infrastructure\Persistence\MySqlIdentityAccessRepository;
use Qmdb\Modules\IdentityAccess\Infrastructure\Security\SecureEmailVerificationTokenGenerator;
use Qmdb\Modules\Tenancy\Infrastructure\Migration\CreateWorkspaceMembershipsMigration;
use Qmdb\Modules\Tenancy\Infrastructure\Migration\CreateWorkspacesMigration;
use Qmdb\Shared\Presentation\Response\FragmentRequestDetector;
use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Tests\Support\MySql\MySqlIntegrationTestCase;

final class P2IdentityAccessHttpIntegrationTest extends MySqlIntegrationTestCase
{
    private PDO $connection;
    private HttpRuntime $runtime;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->provider()->connection();
        $this->rebuildIdentityTables();
        $this->runtime = ApplicationFactory::fromCurrentProcess()->createHttpRuntime();
    }

    protected function tearDown(): void
    {
        $this->clearIdentityRows();
        parent::tearDown();
    }

    public function testRegistrationIsAtomicGenericAndCreatesNoTenantOrSession(): void
    {
        [$csrf, $submission, $cookie] = $this->registrationForm();
        $email = 'p2-' . bin2hex(random_bytes(6)) . '@example.test';
        $response = $this->runtime->handle($this->post('/register', [
            'email' => $email,
            'password' => 'Strong passphrase 123!',
            'password_confirmation' => 'Strong passphrase 123!',
            'csrf_token' => $csrf,
            'registration_submission_id' => $submission,
        ], $cookie));
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/register/accepted', $response->getHeaderLine('Location'));

        $row = $this->query(
            "SELECT a.account_status, e.status_code AS email_status, c.credential_status, "
            . "c.credential_type, c.password_hash FROM user_accounts a "
            . "INNER JOIN account_email_addresses e ON e.user_account_id = a.id "
            . "INNER JOIN account_credentials c ON c.user_account_id = a.id",
        )->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame('PENDING_VERIFICATION', $row['account_status'] ?? null);
        self::assertSame('UNVERIFIED', $row['email_status'] ?? null);
        self::assertSame('ACTIVE', $row['credential_status'] ?? null);
        self::assertSame('PASSWORD', $row['credential_type'] ?? null);
        self::assertIsString($row['password_hash'] ?? null);
        self::assertStringStartsWith('$argon2id$', $row['password_hash']);
        self::assertSame(1, $this->countRows('account_email_verification_challenges'));
        self::assertSame(0, $this->countRows('workspaces'));
        self::assertStringNotContainsString('session', implode('\n', $response->getHeader('Set-Cookie')));
    }

    public function testEnhancedRegistrationIsEnumerationResistantAndCsrfProtected(): void
    {
        $email = 'existing-' . bin2hex(random_bytes(6)) . '@example.test';
        $newBody = $this->enhancedRegistration($email);
        self::assertSame(202, $newBody['response']->getStatusCode());
        self::assertStringNotContainsString($email, $newBody['body']);

        $existingBody = $this->enhancedRegistration($email);
        self::assertSame(202, $existingBody['response']->getStatusCode());
        self::assertSame($newBody['body'], $existingBody['body']);
        self::assertSame(1, $this->countRows('user_accounts'));

        [$csrf, $submission, $cookie] = $this->registrationForm();
        $rejected = $this->runtime->handle($this->post('/register', [
            'email' => 'blocked@example.test',
            'password' => 'Strong passphrase 123!',
            'password_confirmation' => 'Strong passphrase 123!',
            'csrf_token' => $csrf . 'x',
            'registration_submission_id' => $submission,
        ], $cookie));
        self::assertSame(403, $rejected->getStatusCode());
    }

    public function testStaticVerificationRoutesPrecedeChallengeRouteAndGetNeverConsumes(): void
    {
        foreach (['/verify-email/resend', '/verify-email/completed'] as $path) {
            $response = $this->runtime->handle(new ServerRequest('GET', $path));
            self::assertSame(200, $response->getStatusCode());
        }
        $response = $this->runtime->handle(new ServerRequest(
            'GET',
            '/verify-email/01991f93-0b42-7abc-8abc-1234567890ab?token=invalid',
        ));
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame('no-referrer', $response->getHeaderLine('Referrer-Policy'));
        self::assertSame('noindex, nofollow', $response->getHeaderLine('X-Robots-Tag'));
        self::assertSame(0, $this->countRows('account_email_verification_challenges'));
    }

    public function testRegistrationIsLocalizedAccessibleAndRejectsUnsafeMutations(): void
    {
        $arabic = $this->runtime->handle(new ServerRequest('GET', '/register?lang=ar'));
        self::assertSame(200, $arabic->getStatusCode());
        self::assertStringContainsString('<html lang="ar" dir="rtl"', (string)$arabic->getBody());

        [$csrf, $submission, $cookie] = $this->registrationForm();
        $invalid = $this->runtime->handle($this->post('/register', [
            'email' => "invalid\r\n@example.test",
            'password' => 'short',
            'password_confirmation' => 'different',
            'csrf_token' => $csrf,
            'registration_submission_id' => $submission,
        ], $cookie, [
            'Accept' => FragmentRequestDetector::MEDIA_TYPE,
            'X-QMDB-CSRF' => $csrf,
            'Idempotency-Key' => $submission,
        ]));
        $invalidBody = (string)$invalid->getBody();
        self::assertSame(422, $invalid->getStatusCode());
        self::assertStringContainsString('data-qmdb-error-summary role="alert"', $invalidBody);
        self::assertStringContainsString('aria-invalid="true"', $invalidBody);
        self::assertStringNotContainsString("\r", $invalidBody);
        self::assertStringNotContainsString("\n@example.test", $invalidBody);
        self::assertStringNotContainsString('value="short"', $invalidBody);

        $crossOrigin = $this->runtime->handle($this->post('/register', [
            'email' => 'blocked@example.test',
            'password' => 'Strong passphrase 123!',
            'password_confirmation' => 'Strong passphrase 123!',
            'csrf_token' => $csrf,
            'registration_submission_id' => $submission,
        ], $cookie, ['Origin' => 'https://evil.example']));
        self::assertSame(403, $crossOrigin->getStatusCode());

        $unsupported = new ServerRequest(
            'POST',
            '/register',
            ['Content-Type' => 'application/json'],
            '{}',
            '1.1',
            ['REMOTE_ADDR' => '192.0.2.10'],
        );
        $unsupported = $unsupported->withCookieParams($cookie);
        self::assertSame(415, $this->runtime->handle($unsupported)->getStatusCode());
    }

    public function testConcurrentRegistrationUsesOneIdempotentAuthoritativeWrite(): void
    {
        [$csrf, $submission, $cookie] = $this->registrationForm();
        $payload = $this->registrationPayload(
            'concurrent-' . bin2hex(random_bytes(6)) . '@example.test',
            $submission,
            $csrf,
            $cookie,
        );
        $results = $this->runConcurrentMutations(array_fill(0, 4, $payload));

        self::assertSame([303, 303, 303, 303], array_column($results, 'status'));
        self::assertSame(1, $this->countRows('user_accounts'));
        self::assertSame(1, $this->countRows('account_email_addresses'));
        self::assertSame(1, $this->countRows('account_credentials'));
        self::assertSame(1, $this->countRows('account_email_verification_challenges'));
        self::assertSame(1, $this->countRows('identity_idempotency_records'));
        self::assertSame(1, $this->countRows('account_status_events'));
    }

    public function testConcurrentRateLimitAttemptsCannotBypassThreshold(): void
    {
        $email = 'limited-' . bin2hex(random_bytes(6)) . '@example.test';
        $payloads = [];
        for ($attempt = 0; $attempt < 7; $attempt++) {
            [$csrf, $submission, $cookie] = $this->registrationForm();
            $payloads[] = $this->registrationPayload($email, $submission, $csrf, $cookie);
        }
        $results = $this->runConcurrentMutations($payloads);
        $statuses = array_column($results, 'status');

        self::assertSame(5, count(array_filter($statuses, static fn (int $status): bool => $status === 303)));
        self::assertSame(2, count(array_filter($statuses, static fn (int $status): bool => $status === 429)));
        self::assertSame(1, $this->countRows('user_accounts'));
        self::assertSame(2, $this->countRows('identity_rate_limit_buckets'));
    }

    public function testConcurrentVerificationConsumesOnceAndAppendsOneActivationEvent(): void
    {
        [$challengeId, $token] = $this->seedPendingVerificationChallenge();
        $response = $this->runtime->handle(new ServerRequest(
            'GET',
            '/verify-email/' . $challengeId . '?token=' . rawurlencode($token),
        ));
        $body = (string)$response->getBody();
        preg_match('/name="csrf_token" value="([^"]+)"/', $body, $csrf);
        [$pair] = explode(';', $response->getHeaderLine('Set-Cookie'), 2);
        [$cookieName, $cookieValue] = explode('=', $pair, 2);
        self::assertNotSame('', $csrf[1] ?? '');
        if (!isset($csrf[1])) {
            self::fail('Verification form CSRF token is missing.');
        }
        $payload = [
            'path' => '/verify-email/' . $challengeId,
            'fields' => ['csrf_token' => $csrf[1], 'token' => $token],
            'cookies' => [$cookieName => $cookieValue],
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'peer' => '192.0.2.88',
        ];
        $results = $this->runConcurrentMutations([$payload, $payload]);

        self::assertSame([303, 303], array_column($results, 'status'));
        self::assertSame('CONSUMED', $this->scalar(
            'SELECT status FROM account_email_verification_challenges LIMIT 1',
        ));
        self::assertSame('VERIFIED', $this->scalar('SELECT status_code FROM account_email_addresses LIMIT 1'));
        self::assertSame('ACTIVE', $this->scalar('SELECT account_status FROM user_accounts LIMIT 1'));
        self::assertSame(1, (int)$this->scalar(
            "SELECT COUNT(*) FROM account_status_events WHERE event_type = 'ACCOUNT_ACTIVATED_EMAIL_VERIFIED'",
        ));
    }

    /** @return array{string, string, array<string, string>} */
    private function registrationForm(): array
    {
        $response = $this->runtime->handle(new ServerRequest('GET', '/register'));
        $body = (string)$response->getBody();
        self::assertSame(200, $response->getStatusCode());
        preg_match('/name="csrf_token" value="([^"]+)"/', $body, $csrf);
        preg_match('/name="registration_submission_id" value="([^"]+)"/', $body, $submission);
        $setCookie = $response->getHeaderLine('Set-Cookie');
        [$pair] = explode(';', $setCookie, 2);
        [$name, $value] = explode('=', $pair, 2);
        self::assertNotSame('', $csrf[1] ?? '');
        self::assertNotSame('', $submission[1] ?? '');
        if (!isset($csrf[1], $submission[1])) {
            self::fail('Registration form security fields are missing.');
        }

        return [$csrf[1], $submission[1], [$name => $value]];
    }

    /** @return array{response: ResponseInterface, body: string} */
    private function enhancedRegistration(string $email): array
    {
        [$csrf, $submission, $cookie] = $this->registrationForm();
        $response = $this->runtime->handle($this->post('/register', [
            'email' => $email,
            'password' => 'Strong passphrase 123!',
            'password_confirmation' => 'Strong passphrase 123!',
            'csrf_token' => $csrf,
            'registration_submission_id' => $submission,
        ], $cookie, [
            'Accept' => FragmentRequestDetector::MEDIA_TYPE . ', application/problem+json',
            'X-QMDB-CSRF' => $csrf,
            'Idempotency-Key' => $submission,
        ]));

        return ['response' => $response, 'body' => (string)$response->getBody()];
    }

    /**
     * @param array<string, string> $cookie
     * @return array{
     *     path: string,
     *     fields: array<string, string>,
     *     cookies: array<string, string>,
     *     headers: array<string, string>,
     *     peer: string
     * }
     */
    private function registrationPayload(string $email, string $submission, string $csrf, array $cookie): array
    {
        return [
            'path' => '/register',
            'fields' => [
                'email' => $email,
                'password' => 'Strong passphrase 123!',
                'password_confirmation' => 'Strong passphrase 123!',
                'csrf_token' => $csrf,
                'registration_submission_id' => $submission,
            ],
            'cookies' => $cookie,
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'peer' => '192.0.2.77',
        ];
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

    /** @return array{string, string} */
    private function seedPendingVerificationChallenge(): array
    {
        $provider = $this->provider();
        $accounts = new MySqlAccountRepository($provider);
        $access = new MySqlIdentityAccessRepository($provider);
        $now = new DateTimeImmutable();
        $accountInternalId = $accounts->create(new UserAccount(
            null,
            AccountId::generate(),
            AccountStatus::PENDING_VERIFICATION,
            'en',
            'UTC',
            1,
            $now,
            $now,
        ));
        $accounts->appendStatusEvent($accountInternalId, 'ACCOUNT_REGISTERED_PENDING_VERIFICATION', $now);
        $cipher = new SodiumContactCipher(str_repeat('c', 32), 'test-v1');
        $emailInternalId = $accounts->addEmail(
            $accountInternalId,
            AccountEmailId::generate(),
            $cipher->encrypt('verification-concurrency@example.test'),
            $cipher->keyId(),
            LookupHash::keyed(
                'email',
                'verification-concurrency@example.test',
                'qmdb-test-identity-hmac-key-32-bytes-minimum',
            ),
            AccountContactStatus::UNVERIFIED,
            $now,
        );
        $token = (new SecureEmailVerificationTokenGenerator())->generate();
        $challengeId = EmailVerificationChallengeId::generate();
        $access->createChallenge(
            $emailInternalId,
            $challengeId,
            EmailVerificationTokenHash::fromToken($token),
            5,
            $now->modify('+1 hour'),
            $now,
        );

        return [$challengeId->toString(), $token->revealForProof()];
    }

    /** @param array<string, string> $fields
     * @param array<string, string> $cookies
     * @param array<string, string> $headers
     */
    private function post(string $path, array $fields, array $cookies, array $headers = []): ServerRequest
    {
        $request = new ServerRequest(
            'POST',
            $path,
            array_replace(['Content-Type' => 'application/x-www-form-urlencoded'], $headers),
            http_build_query($fields, '', '&', PHP_QUERY_RFC3986),
            '1.1',
            ['REMOTE_ADDR' => '192.0.2.10'],
        );

        return $request->withCookieParams($cookies);
    }

    private function rebuildIdentityTables(): void
    {
        foreach (
            [
                'workspace_role_assignments',
                'platform_role_assignments',
                'authorization_role_permissions',
                'authorization_roles',
                'authorization_permissions',
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
            ] as $table
        ) {
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
        foreach (
            [
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
            ] as $table
        ) {
            $this->connection->exec('DELETE FROM ' . $table);
        }
    }

    private function query(string $sql): \PDOStatement
    {
        $statement = $this->connection->query($sql);
        if (!$statement instanceof \PDOStatement) {
            self::fail('Expected MySQL query statement was not created.');
        }

        return $statement;
    }

    private function countRows(string $table): int
    {
        if (preg_match('/\A[a-z_]+\z/', $table) !== 1) {
            self::fail('Test table identifier is invalid.');
        }
        $value = $this->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
        if (!is_int($value) && (!is_string($value) || preg_match('/\A[0-9]+\z/', $value) !== 1)) {
            self::fail('Expected MySQL row count was not returned.');
        }

        return (int)$value;
    }

    private function scalar(string $sql): int|string
    {
        $value = $this->query($sql)->fetchColumn();
        if (!is_int($value) && !is_string($value)) {
            self::fail('Expected a scalar MySQL value.');
        }

        return $value;
    }

    /** @return list<Migration> */
    private function migrations(): array
    {
        return [
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
}
