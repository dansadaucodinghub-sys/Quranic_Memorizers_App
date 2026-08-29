<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\MySql;

use DateTimeImmutable;
use PDO;
use PDOException;
use Qmdb\Modules\Tenancy\Domain\Value\WorkspaceId;
use Qmdb\Modules\TenancyContext\Application\AccountWorkspaceInventoryHandler;
use Qmdb\Modules\TenancyContext\Application\AccountWorkspaceInventoryQuery;
use Qmdb\Modules\TenancyContext\Application\Background\TenantBoundBackgroundJobContextResolver;
use Qmdb\Modules\TenancyContext\Application\Exception\StaleTenantContextException;
use Qmdb\Modules\TenancyContext\Application\Exception\WorkspaceContextUnavailableException;
use Qmdb\Modules\TenancyContext\Application\SessionTenantContextResolver;
use Qmdb\Modules\TenancyContext\Application\WorkspaceContextSelectionService;
use Qmdb\Modules\TenancyContext\Application\WorkspaceContextClearingService;
use Qmdb\Modules\TenancyContext\Domain\TenantContextVersion;
use Qmdb\Modules\TenancyContext\Infrastructure\Persistence\MySqlSessionTenantContextRepository;
use Qmdb\Modules\TenancyContext\Infrastructure\Persistence\MySqlTenantBoundBackgroundContextRepository;
use Qmdb\Shared\Background\Job\PermanentBackgroundJobFailure;
use Qmdb\Tests\Support\IdentityAccess\FixedIdentityClock;
use Qmdb\Tests\Support\MySql\AuthorizationMySqlFixture;
use Qmdb\Tests\Support\MySql\MySqlIntegrationTestCase;
use Qmdb\Tests\Support\Observability\InMemoryEventLogger;
use Qmdb\Tests\Support\TenancyContext\TestAccountTenantBoundBackgroundJob;

final class P2TenantContextIntegrationTest extends MySqlIntegrationTestCase
{
    private PDO $connection;
    private AuthorizationMySqlFixture $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->provider()->connection();
        $this->fixture = new AuthorizationMySqlFixture($this->connection, dirname(__DIR__, 3));
        $this->fixture->rebuild();
    }

    protected function tearDown(): void
    {
        $this->fixture->dropBusinessTables();
        parent::tearDown();
    }

    public function testSelectionIsAccountAuthorizedVersionedAndIsolatedPerSession(): void
    {
        [$accountInternalId, $accountId] = $this->fixture->account();
        [$workspaceA, $workspaceIdA] = $this->fixture->workspace();
        [$workspaceB, $workspaceIdB] = $this->fixture->workspace();
        $this->fixture->membership($workspaceA, $accountInternalId);
        $this->fixture->membership($workspaceB, $accountInternalId);
        $sessionA = $this->fixture->authenticatedStepUpContext(
            $accountInternalId,
            $accountId,
            'AUTHORIZATION_WORKSPACE_ROLE_ASSIGN',
        );
        $sessionB = $this->fixture->authenticatedStepUpContext(
            $accountInternalId,
            $accountId,
            'AUTHORIZATION_WORKSPACE_ROLE_REVOKE',
        );
        $provider = $this->provider();
        $repository = new MySqlSessionTenantContextRepository($provider);
        $service = new WorkspaceContextSelectionService(
            $repository,
            $this->transactionManager($provider),
            new FixedIdentityClock(new DateTimeImmutable('2026-08-28T13:00:00Z')),
        );

        $selectedA = $service->select($sessionA, $workspaceIdA, new TenantContextVersion(1));
        $selectedB = $service->select($sessionB, $workspaceIdB, new TenantContextVersion(1));

        self::assertSame($workspaceA, $selectedA->workspaceInternalId);
        self::assertSame($workspaceB, $selectedB->workspaceInternalId);
        self::assertSame(2, $selectedA->version->value);
        self::assertSame($workspaceA, $repository->state($sessionA)->context?->workspaceInternalId);
        self::assertSame($workspaceB, $repository->state($sessionB)->context?->workspaceInternalId);
        self::assertSame(1, $sessionA->sessionVersion);
        $this->expectException(StaleTenantContextException::class);
        $service->select($sessionA, $workspaceIdB, new TenantContextVersion(1));
    }

    public function testCrossAccountWorkspaceIsIndistinguishableAndCompositeForeignKeyRejectsInjection(): void
    {
        [$accountA, $accountIdA] = $this->fixture->account();
        [$accountB] = $this->fixture->account();
        [$workspaceA] = $this->fixture->workspace();
        [$workspaceB, $workspaceIdB] = $this->fixture->workspace();
        $this->fixture->membership($workspaceA, $accountA);
        [$membershipB] = $this->fixture->membership($workspaceB, $accountB);
        $session = $this->fixture->authenticatedStepUpContext(
            $accountA,
            $accountIdA,
            'AUTHORIZATION_WORKSPACE_ROLE_ASSIGN',
        );
        $provider = $this->provider();
        $service = new WorkspaceContextSelectionService(
            new MySqlSessionTenantContextRepository($provider),
            $this->transactionManager($provider),
            new FixedIdentityClock(new DateTimeImmutable('2026-08-28T13:00:00Z')),
        );

        try {
            $service->select($session, $workspaceIdB, new TenantContextVersion(1));
            self::fail('A cross-account workspace must not be selectable.');
        } catch (WorkspaceContextUnavailableException $exception) {
            self::assertSame('TENANT_CONTEXT_UNAVAILABLE', $exception->safeCode());
        }

        $statement = $this->connection->prepare(
            'UPDATE user_sessions SET selected_workspace_id = :workspace_id, '
            . 'selected_membership_id = :membership_id, tenant_context_selected_at = :selected_at '
            . 'WHERE id = :session_id',
        );
        $this->expectException(PDOException::class);
        $statement->execute([
            ':workspace_id' => $workspaceB,
            ':membership_id' => $membershipB,
            ':selected_at' => '2026-08-28 13:00:00.000000',
            ':session_id' => $session->sessionInternalId,
        ]);
    }

    public function testInactiveMembershipWorkspaceAndAccountInvalidateAndAtomicallyClearStoredContext(): void
    {
        [$accountInternalId, $accountId] = $this->fixture->account();
        [$workspace, $workspaceId] = $this->fixture->workspace();
        [$membership] = $this->fixture->membership($workspace, $accountInternalId);
        $session = $this->fixture->authenticatedStepUpContext(
            $accountInternalId,
            $accountId,
            'AUTHORIZATION_WORKSPACE_ROLE_ASSIGN',
        );
        $provider = $this->provider();
        $repository = new MySqlSessionTenantContextRepository($provider);
        $transactions = $this->transactionManager($provider);
        $clock = new FixedIdentityClock(new DateTimeImmutable('2026-08-28T13:00:00Z'));
        (new WorkspaceContextSelectionService($repository, $transactions, $clock))->select(
            $session,
            $workspaceId,
            new TenantContextVersion(1),
        );
        $this->fixture->updateMembershipStatus($membership, 'SUSPENDED');

        $resolution = (new SessionTenantContextResolver($repository, $transactions, $clock))->resolve($session);

        self::assertNull($resolution->context);
        self::assertTrue($resolution->invalidSelectionCleared);
        self::assertSame(3, $resolution->version->value);
        $statement = $this->connection->query('SELECT selected_workspace_id, selected_membership_id, '
            . 'tenant_context_selected_at FROM user_sessions WHERE id = ' . $session->sessionInternalId);
        self::assertInstanceOf(\PDOStatement::class, $statement);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertNull($row['selected_workspace_id']);
        self::assertNull($row['selected_membership_id']);
        self::assertNull($row['tenant_context_selected_at']);

        $this->fixture->updateMembershipStatus($membership, 'ACTIVE');
        (new WorkspaceContextSelectionService($repository, $transactions, $clock))->select(
            $session,
            $workspaceId,
            new TenantContextVersion(3),
        );
        $this->fixture->updateWorkspaceStatus($workspace, 'SUSPENDED');
        $workspaceResolution = (new SessionTenantContextResolver(
            $repository,
            $transactions,
            $clock,
        ))->resolve($session);
        self::assertNull($workspaceResolution->context);
        self::assertTrue($workspaceResolution->invalidSelectionCleared);
        self::assertSame(5, $workspaceResolution->version->value);

        $this->fixture->updateWorkspaceStatus($workspace, 'ACTIVE');
        (new WorkspaceContextSelectionService($repository, $transactions, $clock))->select(
            $session,
            $workspaceId,
            new TenantContextVersion(5),
        );
        $this->fixture->updateAccountStatus($accountInternalId, 'SUSPENDED');
        $accountResolution = (new SessionTenantContextResolver($repository, $transactions, $clock))->resolve($session);
        self::assertNull($accountResolution->context);
        self::assertTrue($accountResolution->invalidSelectionCleared);
        self::assertSame(7, $accountResolution->version->value);
    }

    public function testConcurrentCompetingSwitchesAllowExactlyOneExpectedVersionWinner(): void
    {
        [$accountInternalId, $accountId] = $this->fixture->account();
        [$workspaceA] = $this->fixture->workspace();
        [$workspaceB] = $this->fixture->workspace();
        [$membershipA] = $this->fixture->membership($workspaceA, $accountInternalId);
        [$membershipB] = $this->fixture->membership($workspaceB, $accountInternalId);
        $session = $this->fixture->authenticatedStepUpContext(
            $accountInternalId,
            $accountId,
            'AUTHORIZATION_WORKSPACE_ROLE_ASSIGN',
        );

        $results = $this->runWorkers([
            $this->selectPayload($session->sessionInternalId, $accountInternalId, $workspaceA, $membershipA, 1),
            $this->selectPayload($session->sessionInternalId, $accountInternalId, $workspaceB, $membershipB, 1),
        ]);
        sort($results);
        self::assertSame(['selected', 'stale'], $results);
        $row = $this->sessionRow($session->sessionInternalId);
        self::assertSame(2, self::databaseInt($row['tenant_context_version'] ?? null));
        self::assertContains(self::databaseInt($row['selected_workspace_id'] ?? null), [$workspaceA, $workspaceB]);
    }

    public function testConcurrentSwitchAndClearLeaveOneStructurallyValidState(): void
    {
        [$accountInternalId, $accountId] = $this->fixture->account();
        [$workspaceA, $workspaceIdA] = $this->fixture->workspace();
        [$workspaceB] = $this->fixture->workspace();
        $this->fixture->membership($workspaceA, $accountInternalId);
        [$membershipB] = $this->fixture->membership($workspaceB, $accountInternalId);
        $session = $this->fixture->authenticatedStepUpContext(
            $accountInternalId,
            $accountId,
            'AUTHORIZATION_WORKSPACE_ROLE_ASSIGN',
        );
        $provider = $this->provider();
        (new WorkspaceContextSelectionService(
            new MySqlSessionTenantContextRepository($provider),
            $this->transactionManager($provider),
            new FixedIdentityClock(new DateTimeImmutable('2026-08-28T13:00:00Z')),
        ))->select($session, $workspaceIdA, new TenantContextVersion(1));

        $results = $this->runWorkers([
            $this->selectPayload($session->sessionInternalId, $accountInternalId, $workspaceB, $membershipB, 2),
            [
                'operation' => 'clear',
                'session_id' => $session->sessionInternalId,
                'account_id' => $accountInternalId,
                'expected_version' => 2,
            ],
        ]);
        sort($results);
        self::assertTrue(
            $results === ['cleared', 'stale'] || $results === ['selected', 'stale'],
            'Exactly one context mutation must win.',
        );
        $row = $this->sessionRow($session->sessionInternalId);
        self::assertSame(3, self::databaseInt($row['tenant_context_version'] ?? null));
        self::assertSame(
            $row['selected_workspace_id'] === null,
            $row['selected_membership_id'] === null && $row['tenant_context_selected_at'] === null,
        );
    }

    public function testMembershipRevocationAndWorkspaceSuspensionRacesCannotLeaveUsableContext(): void
    {
        foreach (['revoke_membership', 'suspend_workspace'] as $operation) {
            [$accountInternalId, $accountId] = $this->fixture->account();
            [$workspace] = $this->fixture->workspace();
            [$membership] = $this->fixture->membership($workspace, $accountInternalId);
            $session = $this->fixture->authenticatedStepUpContext(
                $accountInternalId,
                $accountId,
                'AUTHORIZATION_WORKSPACE_ROLE_ASSIGN',
            );
            $recordId = $operation === 'revoke_membership' ? $membership : $workspace;
            $this->runWorkers([
                $this->selectPayload($session->sessionInternalId, $accountInternalId, $workspace, $membership, 1),
                ['operation' => $operation, 'record_id' => $recordId],
            ]);
            $provider = $this->provider();
            $repository = new MySqlSessionTenantContextRepository($provider);
            $resolved = (new SessionTenantContextResolver(
                $repository,
                $this->transactionManager($provider),
                new FixedIdentityClock(new DateTimeImmutable('2026-08-28T13:00:00Z')),
            ))->resolve($session);
            self::assertNull($resolved->context);
            self::assertNull($this->sessionRow($session->sessionInternalId)['selected_workspace_id']);
        }
    }

    public function testConcurrentSessionTokenRotationAndSwitchBothSurvive(): void
    {
        [$accountInternalId, $accountId] = $this->fixture->account();
        [$workspace] = $this->fixture->workspace();
        [$membership] = $this->fixture->membership($workspace, $accountInternalId);
        $session = $this->fixture->authenticatedStepUpContext(
            $accountInternalId,
            $accountId,
            'AUTHORIZATION_WORKSPACE_ROLE_ASSIGN',
        );
        $before = $this->sessionRow($session->sessionInternalId);
        $results = $this->runWorkers([
            $this->selectPayload($session->sessionInternalId, $accountInternalId, $workspace, $membership, 1),
            ['operation' => 'rotate_token', 'session_id' => $session->sessionInternalId],
        ]);
        sort($results);
        self::assertSame(['rotated', 'selected'], $results);
        $after = $this->sessionRow($session->sessionInternalId);
        self::assertSame(2, self::databaseInt($after['tenant_context_version'] ?? null));
        self::assertSame(2, self::databaseInt($after['version'] ?? null));
        self::assertNotSame($before['current_token_hash'], $after['current_token_hash']);
        self::assertSame($workspace, self::databaseInt($after['selected_workspace_id'] ?? null));
    }

    public function testBackgroundTenantContextRevalidatesEveryIdentityAndActiveState(): void
    {
        [$accountInternalId, $accountId] = $this->fixture->account();
        [$otherAccountInternalId, $otherAccountId] = $this->fixture->account();
        [$workspaceInternalId, $workspaceId] = $this->fixture->workspace();
        [$otherWorkspaceInternalId, $otherWorkspaceId] = $this->fixture->workspace();
        [$membershipInternalId, $membershipId] = $this->fixture->membership(
            $workspaceInternalId,
            $accountInternalId,
        );
        [, $otherMembershipId] = $this->fixture->membership(
            $otherWorkspaceInternalId,
            $otherAccountInternalId,
        );
        $logger = new InMemoryEventLogger();
        $resolver = new TenantBoundBackgroundJobContextResolver(
            new MySqlTenantBoundBackgroundContextRepository($this->provider()),
            $logger,
        );
        $job = new TestAccountTenantBoundBackgroundJob($accountId, $workspaceId, $membershipId);
        $context = $resolver->resolve($job);
        self::assertSame($accountInternalId, $context->accountInternalId);
        self::assertSame($workspaceInternalId, $context->workspaceInternalId);
        self::assertSame($membershipInternalId, $context->membershipInternalId);

        $this->fixture->updateAccountStatus($accountInternalId, 'SUSPENDED');
        $this->assertPermanentBackgroundFailure(static fn () => $resolver->resolve($job));
        self::assertSame('tenancy.context.background.resolution.failed', $logger->records()[0]['event']);
        self::assertSame([
            'account_public_id',
            'workspace_public_id',
            'membership_public_id',
            'reason_code',
        ], array_keys($logger->records()[0]['context']));
        $this->fixture->updateAccountStatus($accountInternalId, 'ACTIVE');

        foreach (['SUSPENDED', 'CLOSED'] as $status) {
            $this->fixture->updateWorkspaceStatus($workspaceInternalId, $status);
            $this->assertPermanentBackgroundFailure(static fn () => $resolver->resolve($job));
            $this->fixture->updateWorkspaceStatus($workspaceInternalId, 'ACTIVE');
        }
        foreach (['INVITED', 'SUSPENDED', 'REVOKED'] as $status) {
            $this->fixture->updateMembershipStatus($membershipInternalId, $status);
            $this->assertPermanentBackgroundFailure(static fn () => $resolver->resolve($job));
            $this->fixture->updateMembershipStatus($membershipInternalId, 'ACTIVE');
        }
        $this->assertPermanentBackgroundFailure(static fn () => $resolver->resolve(
            new TestAccountTenantBoundBackgroundJob($accountId, $workspaceId, $otherMembershipId),
        ));
        $this->assertPermanentBackgroundFailure(static fn () => $resolver->resolve(
            new TestAccountTenantBoundBackgroundJob($otherAccountId, $otherWorkspaceId, $membershipId),
        ));
    }

    public function testAccountInventoryIsBoundedDeterministicallyCursorPagedAndAccountScoped(): void
    {
        [$accountInternalId, $accountId] = $this->fixture->account();
        [$otherAccountInternalId] = $this->fixture->account();
        for ($index = 0; $index < 3; ++$index) {
            [$workspaceInternalId] = $this->fixture->workspace();
            $this->fixture->membership($workspaceInternalId, $accountInternalId);
        }
        [$otherWorkspaceInternalId] = $this->fixture->workspace();
        $this->fixture->membership($otherWorkspaceInternalId, $otherAccountInternalId);
        $session = $this->fixture->authenticatedStepUpContext(
            $accountInternalId,
            $accountId,
            'AUTHORIZATION_WORKSPACE_ROLE_ASSIGN',
        );
        $provider = $this->provider();
        $repository = new MySqlSessionTenantContextRepository($provider);
        $handler = new AccountWorkspaceInventoryHandler(
            new SessionTenantContextResolver(
                $repository,
                $this->transactionManager($provider),
                new FixedIdentityClock(new DateTimeImmutable('2026-08-28T13:00:00Z')),
            ),
            $repository,
        );

        $first = $handler->handle(new AccountWorkspaceInventoryQuery($session, 1));
        self::assertCount(1, $first->items);
        self::assertNotNull($first->nextCursor);
        $second = $handler->handle(new AccountWorkspaceInventoryQuery(
            $session,
            1,
            WorkspaceId::fromString($first->nextCursor),
        ));
        self::assertCount(1, $second->items);
        self::assertNotSame($first->items[0]->workspaceId, $second->items[0]->workspaceId);
        self::assertSame('ACTIVE', $first->items[0]->workspaceStatus);
        self::assertSame('ACTIVE', $first->items[0]->membershipStatus);
    }

    public function testRepeatedSelectionAndClearingAreIdempotentWithoutVersionChurn(): void
    {
        [$accountInternalId, $accountId] = $this->fixture->account();
        [$workspaceInternalId, $workspaceId] = $this->fixture->workspace();
        $this->fixture->membership($workspaceInternalId, $accountInternalId);
        $session = $this->fixture->authenticatedStepUpContext(
            $accountInternalId,
            $accountId,
            'AUTHORIZATION_WORKSPACE_ROLE_ASSIGN',
        );
        $provider = $this->provider();
        $repository = new MySqlSessionTenantContextRepository($provider);
        $transactions = $this->transactionManager($provider);
        $clock = new FixedIdentityClock(new DateTimeImmutable('2026-08-28T13:00:00Z'));
        $selection = new WorkspaceContextSelectionService($repository, $transactions, $clock);
        $clearing = new WorkspaceContextClearingService($repository, $transactions, $clock);

        self::assertSame(2, $selection->select($session, $workspaceId, new TenantContextVersion(1))->version->value);
        self::assertSame(2, $selection->select($session, $workspaceId, new TenantContextVersion(2))->version->value);
        self::assertSame(3, $clearing->clear($session, new TenantContextVersion(2))->value);
        self::assertSame(3, $clearing->clear($session, new TenantContextVersion(3))->value);
        $row = $this->sessionRow($session->sessionInternalId);
        self::assertSame('ACTIVE', $this->sessionStatus($session->sessionInternalId));
        self::assertSame(3, self::databaseInt($row['tenant_context_version'] ?? null));
    }

    public function testSessionContextChecksRejectPartialZeroAndTimestampOnlySelections(): void
    {
        [$accountInternalId, $accountId] = $this->fixture->account();
        [$workspaceInternalId] = $this->fixture->workspace();
        [$membershipInternalId] = $this->fixture->membership($workspaceInternalId, $accountInternalId);
        $session = $this->fixture->authenticatedStepUpContext(
            $accountInternalId,
            $accountId,
            'AUTHORIZATION_WORKSPACE_ROLE_ASSIGN',
        );
        foreach (
            [
                'tenant_context_version = 0',
                'selected_workspace_id = ' . $workspaceInternalId,
                'selected_membership_id = ' . $membershipInternalId,
                "tenant_context_selected_at = '2026-08-28 13:00:00.000000'",
                'selected_workspace_id = ' . $workspaceInternalId . ', selected_membership_id = '
                    . $membershipInternalId,
            ] as $assignment
        ) {
            $this->assertConstraintRejected('UPDATE user_sessions SET ' . $assignment
                . ' WHERE id = ' . $session->sessionInternalId);
        }
    }

    /** @return array<string, int|string> */
    private function selectPayload(
        int $sessionId,
        int $accountId,
        int $workspaceId,
        int $membershipId,
        int $expectedVersion,
    ): array {
        return [
            'operation' => 'select',
            'session_id' => $sessionId,
            'account_id' => $accountId,
            'workspace_id' => $workspaceId,
            'membership_id' => $membershipId,
            'expected_version' => $expectedVersion,
        ];
    }

    /**
     * @param list<array<string, int|string>> $payloads
     * @return list<string>
     */
    private function runWorkers(array $payloads): array
    {
        $processes = [];
        $startAt = (int)floor(microtime(true) * 1_000_000) + 500_000;
        foreach ($payloads as $payload) {
            $payload['start_at_microseconds'] = $startAt;
            $pipes = [];
            $process = proc_open(
                [PHP_BINARY, dirname(__DIR__, 2) . '/Support/MySql/P2TenantContextMutationWorker.php'],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                dirname(__DIR__, 3),
                null,
                ['bypass_shell' => true],
            );
            self::assertIsResource($process);
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
            self::assertIsString($decoded['status'] ?? null);
            $results[] = $decoded['status'];
        }

        return $results;
    }

    /** @return array<string, mixed> */
    private function sessionRow(int $sessionId): array
    {
        $statement = $this->connection->prepare('SELECT current_token_hash, version, tenant_context_version, '
            . 'selected_workspace_id, selected_membership_id, tenant_context_selected_at '
            . 'FROM user_sessions WHERE id = :session_id');
        $statement->execute([':session_id' => $sessionId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);

        $normalized = [];
        foreach ($row as $key => $value) {
            if (!is_string($key)) {
                throw new \UnexpectedValueException('Session row key is invalid.');
            }
            $normalized[$key] = $value;
        }

        return $normalized;
    }

    private function sessionStatus(int $sessionId): string
    {
        $statement = $this->connection->prepare('SELECT status FROM user_sessions WHERE id = :session_id');
        $statement->execute([':session_id' => $sessionId]);
        $status = $statement->fetchColumn();
        if (!is_string($status)) {
            throw new \UnexpectedValueException('Session status is invalid.');
        }

        return $status;
    }

    private static function databaseInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value) || preg_match('/\A[0-9]+\z/', $value) !== 1) {
            throw new \UnexpectedValueException('Expected database integer is invalid.');
        }

        return (int)$value;
    }

    /** @param \Closure(): mixed $operation */
    private function assertPermanentBackgroundFailure(\Closure $operation): void
    {
        try {
            $operation();
            self::fail('Invalid tenant-bound job context must fail permanently.');
        } catch (PermanentBackgroundJobFailure) {
            self::addToAssertionCount(1);
        }
    }

    private function assertConstraintRejected(string $sql): void
    {
        try {
            $this->connection->exec($sql);
            self::fail('Tenant context constraint must reject invalid state.');
        } catch (PDOException) {
            self::addToAssertionCount(1);
        }
    }
}
