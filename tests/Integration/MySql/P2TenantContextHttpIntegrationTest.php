<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\MySql;

use Nyholm\Psr7\ServerRequest;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Qmdb\Bootstrap\ApplicationFactory;
use Qmdb\Bootstrap\Http\HttpRuntime;
use Qmdb\Modules\Identity\Domain\Value\AccountEmailId;
use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\Identity\Domain\Value\CredentialId;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\EmailLookupHashGenerator;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationMethod;
use Qmdb\Modules\IdentityMultiFactor\Domain\SessionAuthenticationAssurance;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\IdentitySessions\Domain\DeviceId;
use Qmdb\Modules\IdentitySessions\Domain\SessionId;
use Qmdb\Modules\TenancyContext\Application\AccountWorkspaceInventoryHandler;
use Qmdb\Modules\TenancyContext\Application\AccountWorkspaceInventoryQuery;
use Qmdb\Modules\TenancyContext\Application\SessionTenantContextResolver;
use Qmdb\Modules\TenancyContext\Infrastructure\Persistence\MySqlSessionTenantContextRepository;
use Qmdb\Modules\TenancyContext\Interface\Http\TenantContextViewDataFactory;
use Qmdb\Shared\Presentation\Response\FragmentRequestDetector;
use Qmdb\Tests\Support\IdentityAccess\FixedIdentityClock;
use Qmdb\Tests\Support\MySql\AuthorizationMySqlFixture;
use Qmdb\Tests\Support\MySql\MySqlIntegrationTestCase;

final class P2TenantContextHttpIntegrationTest extends MySqlIntegrationTestCase
{
    private const string PASSWORD = 'Strong passphrase 123!';
    private const string HMAC_KEY = 'qmdb-test-identity-hmac-key-32-bytes-minimum';

    private PDO $connection;
    private AuthorizationMySqlFixture $fixture;
    private HttpRuntime $runtime;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->provider()->connection();
        $this->fixture = new AuthorizationMySqlFixture($this->connection, dirname(__DIR__, 3));
        $this->fixture->rebuild();
        $this->runtime = ApplicationFactory::fromCurrentProcess()->createHttpRuntime();
    }

    protected function tearDown(): void
    {
        $this->fixture->dropBusinessTables();
        parent::tearDown();
    }

    public function testWorkspacePagesRequireAuthenticationAndContextWithoutLeakingInventory(): void
    {
        foreach (['/account/workspaces', '/workspace'] as $path) {
            $response = $this->runtime->handle(new ServerRequest('GET', $path));
            self::assertSame(303, $response->getStatusCode());
            self::assertSame('/login', $response->getHeaderLine('Location'));
        }
        $post = $this->runtime->handle($this->post('/account/workspaces/switch', [], []));
        self::assertSame(303, $post->getStatusCode());

        [$accountInternalId, , $email] = $this->seedActiveAccount();
        [$workspaceInternalId, $workspaceId] = $this->fixture->workspace();
        $this->workspaceName($workspaceInternalId, 'Northern Memorization Council');
        $this->fixture->membership($workspaceInternalId, $accountInternalId);
        [$otherAccount] = $this->fixture->account();
        [$otherWorkspace] = $this->fixture->workspace();
        $this->workspaceName($otherWorkspace, 'Other Account Secretariat');
        $this->fixture->membership($otherWorkspace, $otherAccount);
        $cookies = $this->freshLogin($email);
        $authenticated = $this->authenticatedContext($accountInternalId);
        $provider = $this->provider();
        $repository = new MySqlSessionTenantContextRepository($provider);
        $directInventory = (new AccountWorkspaceInventoryHandler(
            new SessionTenantContextResolver(
                $repository,
                $this->transactionManager($provider),
                new FixedIdentityClock(new \DateTimeImmutable('2026-08-28T18:00:00Z')),
            ),
            $repository,
        ))->handle(new AccountWorkspaceInventoryQuery($authenticated));
        self::assertCount(1, $directInventory->items);
        $directView = TenantContextViewDataFactory::inventory($directInventory, 'switch-token', 'clear-token');
        self::assertCount(1, $directView->list('workspaces'));

        $inventory = $this->runtime->handle(
            (new ServerRequest('GET', '/account/workspaces'))->withCookieParams($cookies),
        );
        $body = (string)$inventory->getBody();
        self::assertSame(200, $inventory->getStatusCode(), $body);
        self::assertSame('private, no-store', $inventory->getHeaderLine('Cache-Control'));
        self::assertSame('1', $inventory->getHeaderLine('X-QMDB-Tenant-Context-Version'));
        self::assertStringContainsString('dir="ltr"', $body);
        self::assertStringContainsString('Northern Memorization Council', $body);
        self::assertStringNotContainsString('Other Account Secretariat', $body);
        self::assertStringNotContainsString('workspace_internal_id', $body);
        self::assertStringNotContainsString('membership_internal_id', $body);
        self::assertStringNotContainsString('workspace.owner', $body);
        self::assertStringNotContainsString('workspace.authorization', $body);
        self::assertStringContainsString('name="tenant_context_version" value="1"', $body);
        self::assertStringContainsString('name="workspace_id" value="' . $workspaceId->toString() . '"', $body);
        self::assertStringContainsString('href="/account/security/sessions"', $body);

        $arabic = $this->runtime->handle(
            (new ServerRequest('GET', '/account/workspaces', ['X-QMDB-Locale' => 'ar']))
                ->withCookieParams($cookies),
        );
        self::assertSame(200, $arabic->getStatusCode());
        self::assertSame('ar', $arabic->getHeaderLine('Content-Language'));
        self::assertStringContainsString('dir="rtl"', (string)$arabic->getBody());
    }

    public function testNoJavaScriptAndEnhancedSwitchClearAndStaleResponsesAreSecure(): void
    {
        [$accountInternalId, , $email] = $this->seedActiveAccount();
        [$workspaceInternalId, $workspaceId] = $this->fixture->workspace();
        $this->workspaceName($workspaceInternalId, 'Verified Recitation Workspace');
        $this->fixture->membership($workspaceInternalId, $accountInternalId);
        $cookies = $this->freshLogin($email);
        $inventory = $this->runtime->handle(
            (new ServerRequest('GET', '/account/workspaces'))->withCookieParams($cookies),
        );
        $cookies = array_replace($cookies, $this->cookies($inventory));
        $switchCsrf = $this->formToken((string)$inventory->getBody(), '/account/workspaces/switch');

        $switch = $this->runtime->handle($this->post('/account/workspaces/switch', [
            'csrf_token' => $switchCsrf,
            'tenant_context_version' => '1',
            'workspace_id' => $workspaceId->toString(),
        ], $cookies));
        self::assertSame(303, $switch->getStatusCode(), (string)$switch->getBody());
        self::assertSame('/workspace', $switch->getHeaderLine('Location'));
        self::assertSame('2', $switch->getHeaderLine('X-QMDB-Tenant-Context-Version'));
        self::assertSame('', $switch->getHeaderLine('X-QMDB-Workspace-ID'));

        $current = $this->runtime->handle(
            (new ServerRequest('GET', '/workspace'))->withCookieParams($cookies),
        );
        $currentBody = (string)$current->getBody();
        self::assertSame(200, $current->getStatusCode(), $currentBody);
        self::assertSame('2', $current->getHeaderLine('X-QMDB-Tenant-Context-Version'));
        self::assertStringContainsString('Verified Recitation Workspace', $currentBody);
        self::assertStringNotContainsString('workspace_internal_id', $currentBody);
        self::assertStringNotContainsString('membership_internal_id', $currentBody);
        self::assertStringNotContainsString('workspace.owner', $currentBody);
        self::assertStringContainsString('Workspace status', $currentBody);
        self::assertStringContainsString('Membership status', $currentBody);
        self::assertStringContainsString('This workspace context is active', $currentBody);
        self::assertStringContainsString('href="/account/security/sessions"', $currentBody);
        self::assertStringContainsString('href="/workspace?lang=en"', $currentBody);

        $selectedInventory = $this->runtime->handle(
            (new ServerRequest('GET', '/account/workspaces'))->withCookieParams($cookies),
        );
        $cookies = array_replace($cookies, $this->cookies($selectedInventory));
        $clearCsrf = $this->formToken((string)$selectedInventory->getBody(), '/account/workspaces/clear');
        $clear = $this->runtime->handle($this->post('/account/workspaces/clear', [
            'csrf_token' => $clearCsrf,
            'tenant_context_version' => '2',
        ], $cookies, [
            'Accept' => FragmentRequestDetector::MEDIA_TYPE,
            'X-QMDB-CSRF' => $clearCsrf,
            'X-QMDB-Tenant-Context-Version' => '2',
        ]));
        self::assertSame(200, $clear->getStatusCode(), (string)$clear->getBody());
        self::assertSame('/account/workspaces', $clear->getHeaderLine('X-QMDB-Navigate'));
        self::assertSame('3', $clear->getHeaderLine('X-QMDB-Tenant-Context-Version'));
        $clearCookies = implode('\n', $clear->getHeader('Set-Cookie'));
        self::assertStringNotContainsString('qmdb_session=;', $clearCookies);
        self::assertStringNotContainsString('qmdb_device=;', $clearCookies);

        $withoutContext = $this->runtime->handle(
            (new ServerRequest('GET', '/workspace'))->withCookieParams($cookies),
        );
        self::assertSame(303, $withoutContext->getStatusCode());
        self::assertSame('/account/workspaces?context_required=1', $withoutContext->getHeaderLine('Location'));
        $fragment = $this->runtime->handle(
            (new ServerRequest('GET', '/workspace', ['Accept' => FragmentRequestDetector::MEDIA_TYPE]))
                ->withCookieParams($cookies),
        );
        self::assertSame(409, $fragment->getStatusCode());
        self::assertSame('/account/workspaces', $fragment->getHeaderLine('X-QMDB-Navigate'));
        self::assertStringContainsString('TENANT_CONTEXT_REQUIRED', (string)$fragment->getBody());

        $stale = $this->runtime->handle($this->post('/account/workspaces/switch', [
            'csrf_token' => $switchCsrf,
            'tenant_context_version' => '2',
            'workspace_id' => $workspaceId->toString(),
        ], $cookies, [
            'Accept' => 'application/problem+json',
            'X-QMDB-CSRF' => $switchCsrf,
            'X-QMDB-Tenant-Context-Version' => '2',
        ]));
        self::assertSame(409, $stale->getStatusCode());
        self::assertSame('/account/workspaces', $stale->getHeaderLine('X-QMDB-Navigate'));
        $problem = json_decode((string)$stale->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($problem);
        self::assertSame('TENANT_CONTEXT_STALE', $problem['code'] ?? null);
        self::assertSame('Workspace Context Changed', $problem['title'] ?? null);
        self::assertIsString($problem['request_id'] ?? null);
        self::assertArrayNotHasKey('permission', $problem);
        self::assertArrayNotHasKey('role', $problem);
    }

    public function testSwitchRejectsCsrfUnsupportedMediaCrossAccountAndInvalidatedContext(): void
    {
        [$accountInternalId, , $email] = $this->seedActiveAccount();
        [$workspaceInternalId, $workspaceId] = $this->fixture->workspace();
        [$membershipInternalId] = $this->fixture->membership($workspaceInternalId, $accountInternalId);
        [$otherAccountInternalId] = $this->fixture->account();
        [$otherWorkspaceInternalId, $otherWorkspaceId] = $this->fixture->workspace();
        $this->fixture->membership($otherWorkspaceInternalId, $otherAccountInternalId);
        $cookies = $this->freshLogin($email);
        $inventory = $this->runtime->handle(
            (new ServerRequest('GET', '/account/workspaces'))->withCookieParams($cookies),
        );
        $cookies = array_replace($cookies, $this->cookies($inventory));
        $csrf = $this->formToken((string)$inventory->getBody(), '/account/workspaces/switch');

        $missingCsrf = $this->runtime->handle($this->post('/account/workspaces/switch', [
            'csrf_token' => '',
            'tenant_context_version' => '1',
            'workspace_id' => $workspaceId->toString(),
        ], $cookies));
        self::assertSame(403, $missingCsrf->getStatusCode());
        $unsupported = $this->runtime->handle((new ServerRequest(
            'POST',
            '/account/workspaces/switch',
            ['Content-Type' => 'application/json'],
            '{}',
        ))->withCookieParams($cookies));
        self::assertSame(415, $unsupported->getStatusCode());
        $unexpectedField = $this->runtime->handle($this->post('/account/workspaces/switch', [
            'csrf_token' => $csrf,
            'tenant_context_version' => '1',
            'workspace_id' => $workspaceId->toString(),
            'role' => 'workspace.owner',
        ], $cookies));
        self::assertSame(422, $unexpectedField->getStatusCode());
        $crossAccount = $this->runtime->handle($this->post('/account/workspaces/switch', [
            'csrf_token' => $csrf,
            'tenant_context_version' => '1',
            'workspace_id' => $otherWorkspaceId->toString(),
        ], $cookies));
        self::assertSame(404, $crossAccount->getStatusCode());
        self::assertStringNotContainsString('membership', strtolower((string)$crossAccount->getBody()));

        $selected = $this->runtime->handle($this->post('/account/workspaces/switch', [
            'csrf_token' => $csrf,
            'tenant_context_version' => '1',
            'workspace_id' => $workspaceId->toString(),
        ], $cookies));
        self::assertSame(303, $selected->getStatusCode());
        $this->fixture->updateMembershipStatus($membershipInternalId, 'SUSPENDED');
        $invalidated = $this->runtime->handle(
            (new ServerRequest('GET', '/workspace'))->withCookieParams($cookies),
        );
        self::assertSame(303, $invalidated->getStatusCode());
        self::assertSame('/account/workspaces?context_required=1', $invalidated->getHeaderLine('Location'));
        self::assertSame('3', $invalidated->getHeaderLine('X-QMDB-Tenant-Context-Version'));
        self::assertNull($this->scalar('SELECT selected_workspace_id FROM user_sessions LIMIT 1'));
        self::assertSame(3, (int)$this->scalar('SELECT tenant_context_version FROM user_sessions LIMIT 1'));
    }

    /** @return array{int, AccountId, string} */
    private function seedActiveAccount(): array
    {
        $email = 'tenant-' . bin2hex(random_bytes(6)) . '@example.test';
        $account = AccountId::generate();
        $statement = $this->connection->prepare(
            "INSERT INTO user_accounts (public_id, account_status, preferred_locale, preferred_time_zone, "
            . "version, created_at, updated_at) VALUES "
            . "(:public_id, 'ACTIVE', 'en', 'UTC', 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))",
        );
        $statement->bindValue(':public_id', $account->toBinary(), PDO::PARAM_LOB);
        $statement->execute();
        $accountInternalId = (int)$this->connection->lastInsertId();
        $emailStatement = $this->connection->prepare(
            "INSERT INTO account_email_addresses (public_id, user_account_id, email_ciphertext, encryption_key_id, "
            . "lookup_hash, status_code, verified_at, version, created_at, updated_at) VALUES "
            . "(:public_id, :account_id, :ciphertext, 'test-v1', :lookup_hash, 'VERIFIED', "
            . "UTC_TIMESTAMP(6), 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))",
        );
        $emailStatement->bindValue(':public_id', AccountEmailId::generate()->toBinary(), PDO::PARAM_LOB);
        $emailStatement->bindValue(':account_id', $accountInternalId, PDO::PARAM_INT);
        $emailStatement->bindValue(':ciphertext', 'encrypted-test-contact', PDO::PARAM_LOB);
        $emailStatement->bindValue(
            ':lookup_hash',
            (new EmailLookupHashGenerator(self::HMAC_KEY))->generate($email)->toBinary(),
            PDO::PARAM_LOB,
        );
        $emailStatement->execute();
        $credential = $this->connection->prepare(
            "INSERT INTO account_credentials (public_id, user_account_id, credential_type, credential_status, "
            . "password_hash, algorithm, metadata_version, version, created_at, updated_at) VALUES "
            . "(:public_id, :account_id, 'PASSWORD', 'ACTIVE', :password_hash, 'argon2id', 1, 1, "
            . "UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))",
        );
        $credential->bindValue(':public_id', CredentialId::generate()->toBinary(), PDO::PARAM_LOB);
        $credential->bindValue(':account_id', $accountInternalId, PDO::PARAM_INT);
        $credential->bindValue(':password_hash', password_hash(self::PASSWORD, PASSWORD_ARGON2ID));
        $credential->execute();

        return [$accountInternalId, $account, $email];
    }

    /** @return array<string, string> */
    private function freshLogin(string $email): array
    {
        $form = $this->runtime->handle(new ServerRequest('GET', '/login'));
        $body = (string)$form->getBody();
        preg_match('/name="csrf_token" value="([^"]+)"/', $body, $csrf);
        preg_match('/name="login_submission_id" value="([^"]+)"/', $body, $submission);
        self::assertIsString($csrf[1] ?? null);
        self::assertIsString($submission[1] ?? null);
        $cookies = $this->cookies($form);
        $response = $this->runtime->handle($this->post('/login', [
            'email' => $email,
            'password' => self::PASSWORD,
            'csrf_token' => $csrf[1],
            'login_submission_id' => $submission[1],
        ], $cookies));
        self::assertSame(303, $response->getStatusCode(), (string)$response->getBody());

        return array_replace($cookies, $this->cookies($response));
    }

    private function formToken(string $body, string $action): string
    {
        $quoted = preg_quote($action, '/');
        preg_match('/action="' . $quoted . '"[\s\S]*?name="csrf_token" value="([^"]+)"/', $body, $token);
        self::assertIsString($token[1] ?? null);
        self::assertNotSame('', $token[1]);

        return $token[1];
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

    private function workspaceName(int $workspaceInternalId, string $name): void
    {
        $statement = $this->connection->prepare('UPDATE workspaces SET name = :name WHERE id = :id');
        $statement->execute([':name' => $name, ':id' => $workspaceInternalId]);
    }

    private function authenticatedContext(int $accountInternalId): AuthenticatedAccountContext
    {
        $statement = $this->connection->prepare(
            'SELECT s.id, s.public_id, s.account_id, s.device_id, d.public_id AS device_public_id, '
            . 's.authenticated_at, s.version, s.primary_authentication_method, '
            . 's.secondary_authentication_method, s.assurance_level, s.strong_authenticated_at, '
            . 'a.public_id AS account_public_id FROM user_sessions s '
            . 'INNER JOIN user_devices d ON d.id = s.device_id '
            . 'INNER JOIN user_accounts a ON a.id = s.account_id '
            . 'WHERE s.account_id = :account_id ORDER BY s.id DESC LIMIT 1',
        );
        $statement->execute([':account_id' => $accountInternalId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        $normalized = [];
        foreach ($row as $key => $value) {
            if (!is_string($key)) {
                throw new \UnexpectedValueException('Expected database row key is invalid.');
            }
            $normalized[$key] = $value;
        }
        $row = $normalized;
        foreach (['public_id', 'device_public_id', 'account_public_id', 'authenticated_at'] as $field) {
            self::assertIsString($row[$field] ?? null);
        }
        $authenticatedAt = new \DateTimeImmutable(self::requiredString($row, 'authenticated_at') . ' UTC');
        $secondary = $row['secondary_authentication_method'] ?? null;
        $strong = $row['strong_authenticated_at'] ?? null;

        return new AuthenticatedAccountContext(
            $accountInternalId,
            AccountId::fromBinary(self::requiredString($row, 'account_public_id')),
            self::positiveDatabaseInt($row['id'] ?? null),
            SessionId::fromBinary(self::requiredString($row, 'public_id')),
            self::positiveDatabaseInt($row['device_id'] ?? null),
            DeviceId::fromBinary(self::requiredString($row, 'device_public_id')),
            $authenticatedAt,
            self::positiveDatabaseInt($row['version'] ?? null),
            new SessionAuthenticationAssurance(
                AuthenticationMethod::from(self::requiredString($row, 'primary_authentication_method')),
                is_string($secondary) ? AuthenticationMethod::from($secondary) : null,
                AuthenticationAssuranceLevel::from(self::requiredString($row, 'assurance_level')),
                $authenticatedAt,
                is_string($strong) ? new \DateTimeImmutable($strong . ' UTC') : null,
            ),
        );
    }

    private static function positiveDatabaseInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value) || preg_match('/\A[1-9][0-9]*\z/', $value) !== 1) {
            throw new \UnexpectedValueException('Expected positive database integer is invalid.');
        }

        return (int)$value;
    }

    /** @param array<string, mixed> $row */
    private static function requiredString(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value)) {
            throw new \UnexpectedValueException('Expected database string is invalid.');
        }

        return $value;
    }

    private function scalar(string $sql): int|string|null
    {
        $statement = $this->connection->query($sql);
        self::assertInstanceOf(\PDOStatement::class, $statement);
        $value = $statement->fetchColumn();
        if ($value === false || $value === null) {
            return null;
        }
        return $value;
    }
}
