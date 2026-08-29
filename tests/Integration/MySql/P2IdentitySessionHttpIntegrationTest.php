<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\MySql;

use Nyholm\Psr7\ServerRequest;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Qmdb\Bootstrap\ApplicationFactory;
use Qmdb\Bootstrap\Http\HttpRuntime;
use Qmdb\Modules\Identity\Domain\Value\AccountEmailId;
use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\Identity\Domain\Value\CredentialId;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\EmailLookupHashGenerator;
use Qmdb\Modules\IdentitySessions\Infrastructure\Migration\CreateUserDevicesMigration;
use Qmdb\Modules\IdentitySessions\Infrastructure\Migration\CreateUserSessionsMigration;
use Qmdb\Modules\IdentityMultiFactor\Infrastructure\Migration\CreateAuthenticationTransactionFoundationMigration;
use Qmdb\Modules\IdentityMultiFactor\Infrastructure\Migration\CreatePasskeyFoundationMigration;
use Qmdb\Modules\IdentityMultiFactor\Infrastructure\Migration\CreateTotpRecoveryCodeFoundationMigration;
use Qmdb\Modules\IdentityMultiFactor\Infrastructure\Migration\ExtendIdentityMultiFactorConstraintsMigration;
use Qmdb\Modules\TenancyContext\Infrastructure\Migration\AddSessionBoundTenantContextMigration;
use Qmdb\Shared\Presentation\Response\FragmentRequestDetector;
use Qmdb\Tests\Support\MySql\MySqlIntegrationTestCase;

final class P2IdentitySessionHttpIntegrationTest extends MySqlIntegrationTestCase
{
    private const PASSWORD = 'Strong passphrase 123!';
    private const HMAC_KEY = 'qmdb-test-identity-hmac-key-32-bytes-minimum';

    private PDO $connection;
    private HttpRuntime $runtime;
    private string|false $originalMaximumActiveSessions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalMaximumActiveSessions = getenv('AUTH_SESSION_MAX_ACTIVE_PER_ACCOUNT');
        putenv('AUTH_SESSION_MAX_ACTIVE_PER_ACCOUNT=5');
        $this->connection = $this->provider()->connection();
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
            ] as $table
        ) {
            $this->connection->exec('DROP TABLE IF EXISTS ' . $table);
        }
        $this->connection->exec('DROP TABLE IF EXISTS user_sessions');
        $this->connection->exec('DROP TABLE IF EXISTS user_devices');
        if (
            (int)$this->scalar(
                "SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() "
                . "AND TABLE_NAME = 'workspace_memberships' "
                . "AND INDEX_NAME = 'uq_workspace_memberships_workspace_account_id'",
            ) > 0
        ) {
            $this->connection->exec(
                'ALTER TABLE workspace_memberships DROP INDEX uq_workspace_memberships_workspace_account_id',
            );
        }
        $this->clearRows(false);
        foreach (
            [
            new CreateUserDevicesMigration(),
            new CreateUserSessionsMigration(),
            new ExtendIdentityMultiFactorConstraintsMigration(),
            new CreateAuthenticationTransactionFoundationMigration(),
            new CreateTotpRecoveryCodeFoundationMigration(),
            new CreatePasskeyFoundationMigration(),
            new AddSessionBoundTenantContextMigration(),
            ] as $migration
        ) {
            foreach ($migration->up() as $step) {
                $this->connection->prepare($step->sql())->execute($step->parameters());
            }
        }
        $this->runtime = ApplicationFactory::fromCurrentProcess()->createHttpRuntime();
    }

    protected function tearDown(): void
    {
        $this->clearRows();
        if ($this->originalMaximumActiveSessions === false) {
            putenv('AUTH_SESSION_MAX_ACTIVE_PER_ACCOUNT');
        } else {
            putenv('AUTH_SESSION_MAX_ACTIVE_PER_ACCOUNT=' . $this->originalMaximumActiveSessions);
        }
        parent::tearDown();
    }

    public function testMigrationsCreateGovernedDeviceAndSessionConstraints(): void
    {
        self::assertSame('InnoDB', $this->scalar(
            'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() '
            . "AND TABLE_NAME = 'user_devices'",
        ));
        self::assertSame('binary', strtolower((string)$this->scalar(
            "SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() "
            . "AND TABLE_NAME = 'user_sessions' AND COLUMN_NAME = 'current_token_hash'",
        )));
        self::assertSame(32, (int)$this->scalar(
            "SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() "
            . "AND TABLE_NAME = 'user_sessions' AND COLUMN_NAME = 'current_token_hash'",
        ));
        self::assertSame(1, (int)$this->scalar(
            "SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() "
            . "AND TABLE_NAME = 'user_sessions' AND CONSTRAINT_NAME = 'fk_user_sessions_account_device'",
        ));
        self::assertSame(0, (int)$this->scalar(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() "
            . "AND TABLE_NAME IN ('user_devices','user_sessions') AND COLUMN_NAME = 'workspace_id'",
        ));
    }

    public function testLoginInventoryAndLogoutUseServerSideStateAndSecureCookies(): void
    {
        $email = 'session-' . bin2hex(random_bytes(5)) . '@example.test';
        $this->seedActiveAccount($email);
        [$csrf, $submission, $cookies] = $this->loginForm();

        $invalid = $this->runtime->handle($this->post('/login', [
            'email' => $email,
            'password' => 'Wrong password 123!',
            'csrf_token' => $csrf,
            'login_submission_id' => $submission,
        ], $cookies));
        self::assertSame(422, $invalid->getStatusCode());
        self::assertSame(0, $this->countRows('user_sessions'));
        self::assertSame(0, $this->countRows('user_devices'));
        self::assertStringNotContainsString('SUSPENDED', (string)$invalid->getBody());

        [$response, $authenticatedCookies] = $this->login($email, $cookies, $csrf, $submission);
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/account/security/sessions', $response->getHeaderLine('Location'));
        self::assertArrayHasKey('qmdb_session', $authenticatedCookies);
        self::assertArrayHasKey('qmdb_device', $authenticatedCookies);
        self::assertSame(1, $this->countRows('user_sessions'));
        self::assertSame(1, $this->countRows('user_devices'));
        $sessionCookie = $authenticatedCookies['qmdb_session'];
        self::assertStringNotContainsString($sessionCookie, (string)$this->scalar(
            'SELECT HEX(current_token_hash) FROM user_sessions LIMIT 1',
        ));

        $security = $this->runtime->handle(
            (new ServerRequest('GET', '/account/security/sessions'))->withCookieParams($authenticatedCookies),
        );
        $body = (string)$security->getBody();
        self::assertSame(200, $security->getStatusCode());
        self::assertSame('private, no-store', $security->getHeaderLine('Cache-Control'));
        self::assertStringContainsString('id="account-security-session-panel"', $body);
        self::assertStringNotContainsString($email, $body);
        self::assertStringNotContainsString($sessionCookie, $body);
        preg_match('/action="\/logout"[\s\S]*?name="csrf_token" value="([^"]+)"/', $body, $logoutToken);
        $logoutCsrf = $logoutToken[1] ?? null;
        self::assertIsString($logoutCsrf);
        self::assertNotSame('', $logoutCsrf);

        $logout = $this->runtime->handle($this->post('/logout', [
            'csrf_token' => $logoutCsrf,
        ], $authenticatedCookies));
        self::assertSame(303, $logout->getStatusCode());
        self::assertSame('REVOKED', $this->scalar('SELECT status FROM user_sessions LIMIT 1'));
        self::assertStringContainsString('qmdb_session=;', implode('\n', $logout->getHeader('Set-Cookie')));
        self::assertStringNotContainsString('qmdb_device=;', implode('\n', $logout->getHeader('Set-Cookie')));
    }

    public function testRotationAcceptsPreviousTokenDuringGraceAndRejectsItAfterGrace(): void
    {
        $email = 'rotate-' . bin2hex(random_bytes(5)) . '@example.test';
        $this->seedActiveAccount($email);
        [$csrf, $submission, $cookies] = $this->loginForm();
        [, $authenticatedCookies] = $this->login($email, $cookies, $csrf, $submission);
        $oldCookie = $authenticatedCookies['qmdb_session'];
        $this->connection->exec("UPDATE user_sessions SET rotated_at = UTC_TIMESTAMP(6) - INTERVAL 901 SECOND");

        $first = $this->runtime->handle(
            (new ServerRequest('GET', '/account/security/sessions'))->withCookieParams($authenticatedCookies),
        );
        self::assertSame(200, $first->getStatusCode());
        self::assertStringContainsString('qmdb_session=', implode('\n', $first->getHeader('Set-Cookie')));

        $parallelOld = $this->runtime->handle(
            (new ServerRequest('GET', '/account/security/sessions'))->withCookieParams($authenticatedCookies),
        );
        self::assertSame(200, $parallelOld->getStatusCode());
        self::assertStringNotContainsString('qmdb_session=', implode('\n', $parallelOld->getHeader('Set-Cookie')));
        self::assertSame(1, $this->countRows('user_sessions'));
        self::assertNotSame('', (string)$this->scalar('SELECT HEX(previous_token_hash) FROM user_sessions LIMIT 1'));

        $this->connection->exec(
            "UPDATE user_sessions SET previous_token_expires_at = UTC_TIMESTAMP(6) - INTERVAL 1 SECOND",
        );
        $expiredGrace = $this->runtime->handle(
            (new ServerRequest('GET', '/account/security/sessions'))->withCookieParams([
                ...$authenticatedCookies,
                'qmdb_session' => $oldCookie,
            ]),
        );
        self::assertSame(303, $expiredGrace->getStatusCode());
        self::assertSame('/login', $expiredGrace->getHeaderLine('Location'));
        self::assertStringContainsString('qmdb_session=;', implode('\n', $expiredGrace->getHeader('Set-Cookie')));
    }

    public function testEnhancedLoginUsesSafeNavigationAndDuplicateSubmissionIsRejected(): void
    {
        $email = 'enhanced-' . bin2hex(random_bytes(5)) . '@example.test';
        $this->seedActiveAccount($email);
        [$csrf, $submission, $cookies] = $this->loginForm();
        $request = $this->post('/login', [
            'email' => $email,
            'password' => self::PASSWORD,
            'csrf_token' => $csrf,
            'login_submission_id' => $submission,
        ], $cookies, [
            'Accept' => FragmentRequestDetector::MEDIA_TYPE,
            'X-QMDB-CSRF' => $csrf,
            'Idempotency-Key' => $submission,
        ]);
        $success = $this->runtime->handle($request);
        self::assertSame(200, $success->getStatusCode(), (string)$success->getBody());
        self::assertSame('/account/security/sessions', $success->getHeaderLine('X-QMDB-Navigate'));
        self::assertSame(1, $this->countRows('user_sessions'));

        $replayed = $this->runtime->handle($request);
        self::assertSame(409, $replayed->getStatusCode());
        self::assertSame(1, $this->countRows('user_sessions'));
        self::assertSame('', $replayed->getHeaderLine('X-QMDB-Navigate'));
    }

    public function testRemoteSessionAndDeviceRevocationAreOwnershipScopedAndOptimistic(): void
    {
        $email = 'revoke-' . bin2hex(random_bytes(5)) . '@example.test';
        $this->seedActiveAccount($email);
        $currentCookies = $this->freshLogin($email);
        $remoteSessionCookies = $this->freshLogin($email);
        $remoteDeviceCookies = $this->freshLogin($email);
        $currentSession = $this->selector($currentCookies['qmdb_session']);
        $remoteSession = $this->selector($remoteSessionCookies['qmdb_session']);
        $remoteDevice = $this->selector($remoteDeviceCookies['qmdb_device']);

        $currentConflict = $this->runtime->handle(
            (new ServerRequest('GET', '/account/security/sessions/' . $currentSession . '/revoke'))
                ->withCookieParams($currentCookies),
        );
        self::assertSame(409, $currentConflict->getStatusCode());

        $sessionForm = $this->runtime->handle(
            (new ServerRequest('GET', '/account/security/sessions/' . $remoteSession . '/revoke'))
                ->withCookieParams($currentCookies),
        );
        self::assertSame(200, $sessionForm->getStatusCode());
        [$sessionCsrf, $sessionVersion] = $this->revocationFields((string)$sessionForm->getBody());
        $sessionCookies = array_replace($currentCookies, $this->cookies($sessionForm));
        $revokedSession = $this->runtime->handle($this->post(
            '/account/security/sessions/' . $remoteSession . '/revoke',
            ['csrf_token' => $sessionCsrf, 'expected_version' => $sessionVersion],
            $sessionCookies,
        ));
        self::assertSame(303, $revokedSession->getStatusCode());
        self::assertSame('REVOKED', $this->scalar(
            "SELECT status FROM user_sessions WHERE public_id = UNHEX(REPLACE('$remoteSession', '-', ''))",
        ));
        self::assertSame('REMOTE_SESSION_REVOCATION', $this->scalar(
            "SELECT revoke_reason_code FROM user_sessions WHERE public_id = UNHEX(REPLACE('$remoteSession', '-', ''))",
        ));

        $deviceForm = $this->runtime->handle(
            (new ServerRequest('GET', '/account/security/devices/' . $remoteDevice . '/revoke'))
                ->withCookieParams($sessionCookies),
        );
        self::assertSame(200, $deviceForm->getStatusCode());
        [$deviceCsrf, $deviceVersion] = $this->revocationFields((string)$deviceForm->getBody());
        $deviceCookies = array_replace($sessionCookies, $this->cookies($deviceForm));
        $revokedDevice = $this->runtime->handle($this->post(
            '/account/security/devices/' . $remoteDevice . '/revoke',
            ['csrf_token' => $deviceCsrf, 'expected_version' => $deviceVersion],
            $deviceCookies,
        ));
        self::assertSame(303, $revokedDevice->getStatusCode());
        self::assertSame('REVOKED', $this->scalar(
            "SELECT status FROM user_devices WHERE public_id = UNHEX(REPLACE('$remoteDevice', '-', ''))",
        ));
        self::assertSame(1, (int)$this->scalar(
            "SELECT COUNT(*) FROM user_sessions s INNER JOIN user_devices d ON d.id = s.device_id "
            . "WHERE d.public_id = UNHEX(REPLACE('$remoteDevice', '-', '')) "
            . "AND s.status = 'REVOKED' AND s.revoke_reason_code = 'DEVICE_REVOCATION'",
        ));

        $staleRetry = $this->runtime->handle($this->post(
            '/account/security/devices/' . $remoteDevice . '/revoke',
            ['csrf_token' => $deviceCsrf, 'expected_version' => $deviceVersion],
            $deviceCookies,
        ));
        self::assertSame(303, $staleRetry->getStatusCode());

        $otherEmail = 'other-' . bin2hex(random_bytes(5)) . '@example.test';
        $this->seedActiveAccount($otherEmail);
        $otherCookies = $this->freshLogin($otherEmail);
        $otherTarget = $this->selector($otherCookies['qmdb_session']);
        $crossAccount = $this->runtime->handle(
            (new ServerRequest('GET', '/account/security/sessions/' . $otherTarget . '/revoke'))
                ->withCookieParams($currentCookies),
        );
        self::assertSame(404, $crossAccount->getStatusCode());
    }

    public function testRealParallelRotationAndTouchUseIndependentProcessesSafely(): void
    {
        $email = 'parallel-' . bin2hex(random_bytes(5)) . '@example.test';
        $this->seedActiveAccount($email);
        $cookies = $this->freshLogin($email);
        $payload = $this->authenticatedGetPayload($cookies);

        $this->connection->exec(
            "UPDATE user_sessions SET rotated_at = UTC_TIMESTAMP(6) - INTERVAL 901 SECOND, "
            . 'last_seen_at = UTC_TIMESTAMP(6)',
        );
        $rotation = $this->runConcurrentMutations([$payload, $payload]);
        self::assertSame([200, 200], array_column($rotation, 'status'));
        $sessionCookieWrites = 0;
        foreach ($rotation as $result) {
            foreach ($result['set_cookies'] as $header) {
                if (str_starts_with($header, 'qmdb_session=')) {
                    ++$sessionCookieWrites;
                }
            }
        }
        self::assertSame(1, $sessionCookieWrites);
        self::assertSame(1, $this->countRows('user_sessions'));
        self::assertNotSame('', (string)$this->scalar('SELECT HEX(previous_token_hash) FROM user_sessions LIMIT 1'));

        $newCookieHeader = null;
        foreach ($rotation as $result) {
            foreach ($result['set_cookies'] as $header) {
                if (str_starts_with($header, 'qmdb_session=')) {
                    $newCookieHeader = $header;
                }
            }
        }
        self::assertIsString($newCookieHeader);
        $rotatedCookies = array_replace($cookies, $this->cookieFromHeader($newCookieHeader));
        $this->connection->exec(
            'UPDATE user_sessions SET issued_at = issued_at - INTERVAL 301 SECOND, '
            . 'authenticated_at = authenticated_at - INTERVAL 301 SECOND, '
            . 'last_seen_at = last_seen_at - INTERVAL 301 SECOND, rotated_at = UTC_TIMESTAMP(6)',
        );
        $touch = $this->runConcurrentMutations([
            $this->authenticatedGetPayload($rotatedCookies),
            $this->authenticatedGetPayload($rotatedCookies),
        ]);
        self::assertSame([200, 200], array_column($touch, 'status'));
        self::assertSame(1, $this->countRows('user_sessions'));
    }

    public function testConcurrentLoginsEnforceMaximumActiveSessionLimit(): void
    {
        $email = 'limit-' . bin2hex(random_bytes(5)) . '@example.test';
        $this->seedActiveAccount($email);
        [$csrf, , $cookies] = $this->loginForm();
        $payloads = [];
        for ($index = 0; $index < 6; ++$index) {
            $submission = \Qmdb\Modules\IdentitySessions\Domain\LoginSubmissionId::generate()->toString();
            $payloads[] = [
                'method' => 'POST',
                'path' => '/login',
                'fields' => [
                    'email' => $email,
                    'password' => self::PASSWORD,
                    'csrf_token' => $csrf,
                    'login_submission_id' => $submission,
                ],
                'cookies' => $cookies,
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'peer' => '192.0.2.' . (100 + $index),
            ];
        }
        $results = $this->runConcurrentMutations($payloads);
        self::assertSame([303, 303, 303, 303, 303, 303], array_column($results, 'status'));
        self::assertSame(5, (int)$this->scalar(
            "SELECT COUNT(*) FROM user_sessions WHERE status = 'ACTIVE'",
        ));
        self::assertSame(1, (int)$this->scalar(
            "SELECT COUNT(*) FROM user_sessions WHERE status = 'REVOKED' AND revoke_reason_code = 'SESSION_LIMIT'",
        ));
    }

    public function testAuthenticationStateEnforcesAccountDeviceAndExpirationOnEveryRequest(): void
    {
        $email = 'state-' . bin2hex(random_bytes(5)) . '@example.test';
        $this->seedActiveAccount($email);
        $cookies = $this->freshLogin($email);

        $this->connection->exec("UPDATE user_accounts SET account_status = 'SUSPENDED'");
        $suspended = $this->runtime->handle(
            (new ServerRequest('GET', '/account/security/sessions'))->withCookieParams($cookies),
        );
        self::assertSame(303, $suspended->getStatusCode());
        self::assertStringContainsString('qmdb_session=;', implode('\n', $suspended->getHeader('Set-Cookie')));

        $this->connection->exec("UPDATE user_accounts SET account_status = 'ACTIVE'");
        $this->connection->exec(
            "UPDATE user_devices SET status = 'REVOKED', revoked_at = UTC_TIMESTAMP(6), version = version + 1",
        );
        $revokedDevice = $this->runtime->handle(
            (new ServerRequest('GET', '/account/security/sessions'))->withCookieParams($cookies),
        );
        self::assertSame(303, $revokedDevice->getStatusCode());
        self::assertStringContainsString('qmdb_session=;', implode('\n', $revokedDevice->getHeader('Set-Cookie')));

        $this->connection->exec(
            "UPDATE user_devices SET status = 'ACTIVE', revoked_at = NULL, version = version + 1",
        );
        $this->connection->exec(
            'UPDATE user_sessions SET issued_at = issued_at - INTERVAL 600 SECOND, '
            . 'authenticated_at = authenticated_at - INTERVAL 600 SECOND, '
            . 'last_seen_at = last_seen_at - INTERVAL 600 SECOND, '
            . 'idle_expires_at = UTC_TIMESTAMP(6) - INTERVAL 1 SECOND',
        );
        $expired = $this->runtime->handle(
            (new ServerRequest('GET', '/account/security/sessions'))->withCookieParams($cookies),
        );
        self::assertSame(303, $expired->getStatusCode());
        self::assertSame('EXPIRED', $this->scalar('SELECT status FROM user_sessions LIMIT 1'));
        self::assertStringContainsString('qmdb_session=;', implode('\n', $expired->getHeader('Set-Cookie')));

        $malformed = $this->runtime->handle(
            (new ServerRequest('GET', '/account/security/sessions'))->withCookieParams([
                'qmdb_session' => 'v1.invalid.invalid',
            ]),
        );
        self::assertSame(303, $malformed->getStatusCode());
        self::assertStringContainsString('qmdb_session=;', implode('\n', $malformed->getHeader('Set-Cookie')));

        $fragment = $this->runtime->handle(new ServerRequest(
            'GET',
            '/account/security/sessions',
            ['Accept' => FragmentRequestDetector::MEDIA_TYPE],
        ));
        self::assertSame(401, $fragment->getStatusCode());
        self::assertSame('/login', $fragment->getHeaderLine('X-QMDB-Navigate'));
    }

    public function testSuccessfulReauthenticationPreventsFixationWhileFailurePreservesCurrentSession(): void
    {
        $email = 'fixation-' . bin2hex(random_bytes(5)) . '@example.test';
        $this->seedActiveAccount($email);
        $currentCookies = $this->freshLogin($email);
        $oldCookie = $currentCookies['qmdb_session'];

        $alreadyAuthenticated = $this->runtime->handle(
            (new ServerRequest('GET', '/login'))->withCookieParams($currentCookies),
        );
        self::assertSame(303, $alreadyAuthenticated->getStatusCode());
        self::assertSame('/account/security/sessions', $alreadyAuthenticated->getHeaderLine('Location'));

        [$csrf, $submission, $anonymousCookies] = $this->loginForm();
        [$reauthenticated, $newCookies] = $this->login(
            $email,
            array_replace($currentCookies, $anonymousCookies),
            $csrf,
            $submission,
        );
        self::assertSame(303, $reauthenticated->getStatusCode());
        self::assertNotSame($oldCookie, $newCookies['qmdb_session']);
        self::assertSame(1, (int)$this->scalar(
            "SELECT COUNT(*) FROM user_sessions WHERE status = 'REVOKED' "
            . "AND revoke_reason_code = 'REAUTHENTICATION'",
        ));
        self::assertSame(1, (int)$this->scalar("SELECT COUNT(*) FROM user_sessions WHERE status = 'ACTIVE'"));

        [$failedCsrf, $failedSubmission, $failedFormCookies] = $this->loginForm();
        $failed = $this->runtime->handle($this->post('/login', [
            'email' => $email,
            'password' => 'Wrong passphrase 123!',
            'csrf_token' => $failedCsrf,
            'login_submission_id' => $failedSubmission,
        ], array_replace($newCookies, $failedFormCookies)));
        self::assertSame(422, $failed->getStatusCode());
        self::assertSame(1, (int)$this->scalar("SELECT COUNT(*) FROM user_sessions WHERE status = 'ACTIVE'"));
    }

    public function testAccountStatesAreRejectedGenericallyAndLegacyPasswordHashIsRehashed(): void
    {
        foreach (['PENDING_VERIFICATION', 'SUSPENDED', 'CLOSED'] as $status) {
            $email = strtolower($status) . '-' . bin2hex(random_bytes(4)) . '@example.test';
            $this->seedActiveAccount($email, $status);
            [$csrf, $submission, $cookies] = $this->loginForm();
            [$response] = $this->login($email, $cookies, $csrf, $submission);
            self::assertSame(422, $response->getStatusCode());
            self::assertStringNotContainsString($status, (string)$response->getBody());
        }
        self::assertSame(0, $this->countRows('user_sessions'));
        self::assertSame(0, $this->countRows('user_devices'));

        $legacyEmail = 'rehash-' . bin2hex(random_bytes(5)) . '@example.test';
        $this->seedActiveAccount($legacyEmail, 'ACTIVE', PASSWORD_BCRYPT);
        $this->freshLogin($legacyEmail);
        $rehash = (string)$this->scalar(
            'SELECT password_hash FROM account_credentials c INNER JOIN account_email_addresses e '
            . 'ON e.user_account_id = c.user_account_id ORDER BY c.id DESC LIMIT 1',
        );
        self::assertStringStartsWith('$argon2id$', $rehash);
    }

    /** @return array{string, string, array<string, string>} */
    private function loginForm(): array
    {
        $response = $this->runtime->handle(new ServerRequest('GET', '/login'));
        $body = (string)$response->getBody();
        preg_match('/name="csrf_token" value="([^"]+)"/', $body, $csrf);
        preg_match('/name="login_submission_id" value="([^"]+)"/', $body, $submission);
        self::assertSame(200, $response->getStatusCode());
        $csrfValue = $csrf[1] ?? null;
        $submissionValue = $submission[1] ?? null;
        self::assertIsString($csrfValue);
        self::assertIsString($submissionValue);
        self::assertNotSame('', $csrfValue);
        self::assertNotSame('', $submissionValue);

        return [$csrfValue, $submissionValue, $this->cookies($response)];
    }

    /**
     * @param array<string, string> $cookies
     * @return array{\Psr\Http\Message\ResponseInterface, array<string, string>}
     */
    private function login(
        string $email,
        array $cookies,
        string $csrf,
        string $submission,
    ): array {
        $response = $this->runtime->handle($this->post('/login', [
            'email' => $email,
            'password' => self::PASSWORD,
            'csrf_token' => $csrf,
            'login_submission_id' => $submission,
        ], $cookies));

        return [$response, array_replace($cookies, $this->cookies($response))];
    }

    /** @return array<string, string> */
    private function freshLogin(string $email): array
    {
        [$csrf, $submission, $cookies] = $this->loginForm();
        [$response, $authenticatedCookies] = $this->login($email, $cookies, $csrf, $submission);
        self::assertSame(303, $response->getStatusCode());

        return $authenticatedCookies;
    }

    private function selector(string $cookie): string
    {
        $parts = explode('.', $cookie);
        $selector = $parts[1] ?? '';
        self::assertNotSame('', $selector);

        return $selector;
    }

    /** @return array{string, string} */
    private function revocationFields(string $body): array
    {
        preg_match('/name="csrf_token" value="([^"]+)"/', $body, $csrf);
        preg_match('/name="expected_version" value="([0-9]+)"/', $body, $version);
        $csrfValue = $csrf[1] ?? null;
        $versionValue = $version[1] ?? null;
        self::assertIsString($csrfValue);
        self::assertIsString($versionValue);
        self::assertNotSame('', $csrfValue);
        self::assertNotSame('', $versionValue);

        return [$csrfValue, $versionValue];
    }

    /**
     * @param array<string, string> $cookies
     * @return array{
     *     method: string,
     *     path: string,
     *     fields: array<string, string>,
     *     cookies: array<string, string>,
     *     headers: array<string, string>,
     *     peer: string
     * }
     */
    private function authenticatedGetPayload(array $cookies): array
    {
        return [
            'method' => 'GET',
            'path' => '/account/security/sessions',
            'fields' => [],
            'cookies' => $cookies,
            'headers' => [],
            'peer' => '192.0.2.88',
        ];
    }

    /**
     * @param list<array<string, mixed>> $payloads
     * @return list<array{status: int, location: string, set_cookies: list<string>}>
     */
    private function runConcurrentMutations(array $payloads): array
    {
        $processes = [];
        $worker = dirname(__DIR__, 2) . '/Support/Http/P2MutationWorker.php';
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
            $setCookies = $decoded['set_cookies'] ?? null;
            self::assertIsInt($status);
            self::assertIsString($location);
            self::assertIsArray($setCookies);
            $normalizedSetCookies = [];
            foreach ($setCookies as $setCookie) {
                self::assertIsString($setCookie);
                $normalizedSetCookies[] = $setCookie;
            }
            $results[] = [
                'status' => $status,
                'location' => $location,
                'set_cookies' => $normalizedSetCookies,
            ];
        }
        usort($results, static fn (array $left, array $right): int => $left['status'] <=> $right['status']);

        return $results;
    }

    /** @return array<string, string> */
    private function cookieFromHeader(string $header): array
    {
        [$pair] = explode(';', $header, 2);
        [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');

        return [$name => $value];
    }

    private function seedActiveAccount(
        string $email,
        string $status = 'ACTIVE',
        string|int|null $passwordAlgorithm = PASSWORD_ARGON2ID,
    ): void {
        $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
        $account = AccountId::generate();
        $statement = $this->connection->prepare(
            "INSERT INTO user_accounts (public_id, account_status, preferred_locale, preferred_time_zone, "
            . 'version, created_at, updated_at) VALUES '
            . '(:public_id, :status, \'en\', \'UTC\', 1, :created_at, :updated_at)',
        );
        $statement->bindValue(':public_id', $account->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':status', $status);
        $statement->bindValue(':created_at', $now);
        $statement->bindValue(':updated_at', $now);
        $statement->execute();
        $accountInternalId = (int)$this->connection->lastInsertId();
        $emailStatement = $this->connection->prepare(
            "INSERT INTO account_email_addresses (public_id, user_account_id, email_ciphertext, encryption_key_id, "
            . "lookup_hash, status_code, verified_at, version, created_at, updated_at) VALUES "
            . "(:public_id, :account_id, :ciphertext, 'test-v1', :lookup_hash, 'VERIFIED', :verified_at, 1, "
            . ':created_at, :updated_at)',
        );
        $emailStatement->bindValue(':public_id', AccountEmailId::generate()->toBinary(), PDO::PARAM_LOB);
        $emailStatement->bindValue(':account_id', $accountInternalId, PDO::PARAM_INT);
        $emailStatement->bindValue(':ciphertext', 'encrypted-test-contact', PDO::PARAM_LOB);
        $emailStatement->bindValue(
            ':lookup_hash',
            (new EmailLookupHashGenerator(self::HMAC_KEY))->generate($email)->toBinary(),
            PDO::PARAM_LOB,
        );
        foreach (['verified_at', 'created_at', 'updated_at'] as $name) {
            $emailStatement->bindValue(':' . $name, $now);
        }
        $emailStatement->execute();
        $credential = $this->connection->prepare(
            "INSERT INTO account_credentials (public_id, user_account_id, credential_type, credential_status, "
            . "password_hash, algorithm, metadata_version, version, created_at, updated_at) VALUES "
            . "(:public_id, :account_id, 'PASSWORD', 'ACTIVE', :password_hash, 'argon2id', 1, 1, "
            . ':created_at, :updated_at)',
        );
        $credential->bindValue(':public_id', CredentialId::generate()->toBinary(), PDO::PARAM_LOB);
        $credential->bindValue(':account_id', $accountInternalId, PDO::PARAM_INT);
        $credential->bindValue(':password_hash', password_hash(self::PASSWORD, $passwordAlgorithm));
        $credential->bindValue(':created_at', $now);
        $credential->bindValue(':updated_at', $now);
        $credential->execute();
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
            ['REMOTE_ADDR' => '192.0.2.55'],
        ))->withCookieParams($cookies);
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

    private function clearRows(bool $includeSessionTables = true): void
    {
        $tables = [
            'account_security_notification_events',
            'account_security_notifications',
            'account_password_recovery_events',
            'account_password_recovery_challenges',
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
        if ($includeSessionTables) {
            array_unshift($tables, 'user_sessions', 'user_devices');
        }

        foreach ($tables as $table) {
            $this->connection->exec('DELETE FROM ' . $table);
        }
    }
}
