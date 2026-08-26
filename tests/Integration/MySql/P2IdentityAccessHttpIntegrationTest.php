<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\MySql;

use Nyholm\Psr7\ServerRequest;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Qmdb\Bootstrap\ApplicationFactory;
use Qmdb\Bootstrap\Http\HttpRuntime;
use Qmdb\Modules\Identity\Infrastructure\Migration\CreateAccountSecurityFoundationMigration;
use Qmdb\Modules\Identity\Infrastructure\Migration\CreateUserAccountsMigration;
use Qmdb\Modules\IdentityAccess\Infrastructure\Migration\CreateIdentityRateLimitFoundationMigration;
use Qmdb\Modules\IdentityAccess\Infrastructure\Migration\CreateIdentityVerificationFoundationMigration;
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
        $this->connection->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach (
            [
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
        $this->connection->exec('SET FOREIGN_KEY_CHECKS=1');
        foreach ($this->migrations() as $migration) {
            foreach ($migration->up() as $step) {
                $this->connection->prepare($step->sql())->execute($step->parameters());
            }
        }
    }

    private function clearIdentityRows(): void
    {
        $this->connection->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach (
            [
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
        $this->connection->exec('SET FOREIGN_KEY_CHECKS=1');
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
        ];
    }
}
