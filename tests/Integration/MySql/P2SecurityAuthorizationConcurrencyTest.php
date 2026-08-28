<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\MySql;

use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Tests\Support\MySql\AuthorizationMySqlFixture;
use Qmdb\Tests\Support\MySql\MySqlIntegrationTestCase;

final class P2SecurityAuthorizationConcurrencyTest extends MySqlIntegrationTestCase
{
    private AuthorizationMySqlFixture $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        $connection = $this->provider()->connection();
        $this->fixture = new AuthorizationMySqlFixture($connection, dirname(__DIR__, 3));
        $this->fixture->rebuild();
    }

    protected function tearDown(): void
    {
        $this->fixture->dropBusinessTables();
        parent::tearDown();
    }

    public function testConcurrentDuplicatePlatformAndWorkspaceAssignmentsProduceExactlyOneActiveRow(): void
    {
        [$accountInternalId] = $this->fixture->account();
        $platformPayload = json_encode([
            'operation' => 'assign_platform',
            'account_id' => $accountInternalId,
            'actor_id' => $accountInternalId,
            'role_id' => $this->fixture->roleInternalId('platform.authorization_auditor'),
        ], JSON_THROW_ON_ERROR);
        $platformResults = $this->runWorkers(
            dirname(__DIR__, 2) . '/Support/MySql/P2AuthorizationMutationWorker.php',
            [$platformPayload, $platformPayload],
        );
        sort($platformResults);
        self::assertSame([false, true], $platformResults);
        self::assertSame(1, $this->fixture->activePlatformAssignmentCount(
            $accountInternalId,
            'platform.authorization_auditor',
        ));

        [$workspaceInternalId] = $this->fixture->workspace();
        [$membershipInternalId] = $this->fixture->membership($workspaceInternalId, $accountInternalId);
        $workspacePayload = json_encode([
            'operation' => 'assign_workspace',
            'workspace_id' => $workspaceInternalId,
            'membership_id' => $membershipInternalId,
            'actor_id' => $accountInternalId,
            'role_id' => $this->fixture->roleInternalId('workspace.viewer'),
        ], JSON_THROW_ON_ERROR);
        $workspaceResults = $this->runWorkers(
            dirname(__DIR__, 2) . '/Support/MySql/P2AuthorizationMutationWorker.php',
            [$workspacePayload, $workspacePayload],
        );
        sort($workspaceResults);
        self::assertSame([false, true], $workspaceResults);
        self::assertSame(1, $this->fixture->activeWorkspaceAssignmentCount(
            $workspaceInternalId,
            $membershipInternalId,
        ));
    }

    public function testConcurrentRevocationsPreserveOnePlatformAdministratorAndOneWorkspaceOwner(): void
    {
        [$firstAccount] = $this->fixture->account();
        [$secondAccount] = $this->fixture->account();
        $firstAdmin = $this->fixture->platformAssignment(
            $firstAccount,
            'platform.security_administrator',
            $firstAccount,
        );
        $secondAdmin = $this->fixture->platformAssignment(
            $secondAccount,
            'platform.security_administrator',
            $secondAccount,
        );
        $platformResults = $this->runWorkers(
            dirname(__DIR__, 2) . '/Support/MySql/P2AuthorizationMutationWorker.php',
            [
                json_encode([
                    'operation' => 'revoke_platform_administrator',
                    'assignment_id' => $this->fixture->platformAssignmentInternalId($firstAdmin),
                ], JSON_THROW_ON_ERROR),
                json_encode([
                    'operation' => 'revoke_platform_administrator',
                    'assignment_id' => $this->fixture->platformAssignmentInternalId($secondAdmin),
                ], JSON_THROW_ON_ERROR),
            ],
        );
        sort($platformResults);
        self::assertSame([false, true], $platformResults);
        self::assertSame(1, $this->fixture->activePlatformSecurityAdministratorCount());

        [$workspaceInternalId] = $this->fixture->workspace();
        [$firstMembership] = $this->fixture->membership($workspaceInternalId, $firstAccount);
        [$secondMembership] = $this->fixture->membership($workspaceInternalId, $secondAccount);
        $firstOwner = $this->fixture->workspaceAssignment(
            $workspaceInternalId,
            $firstMembership,
            'workspace.owner',
            $firstAccount,
        );
        $secondOwner = $this->fixture->workspaceAssignment(
            $workspaceInternalId,
            $secondMembership,
            'workspace.owner',
            $secondAccount,
        );
        $workspaceResults = $this->runWorkers(
            dirname(__DIR__, 2) . '/Support/MySql/P2AuthorizationMutationWorker.php',
            [
                json_encode([
                    'operation' => 'revoke_workspace_owner',
                    'assignment_id' => $this->fixture->workspaceAssignmentInternalId($firstOwner),
                    'workspace_id' => $workspaceInternalId,
                ], JSON_THROW_ON_ERROR),
                json_encode([
                    'operation' => 'revoke_workspace_owner',
                    'assignment_id' => $this->fixture->workspaceAssignmentInternalId($secondOwner),
                    'workspace_id' => $workspaceInternalId,
                ], JSON_THROW_ON_ERROR),
            ],
        );
        sort($workspaceResults);
        self::assertSame([false, true], $workspaceResults);
        self::assertSame(1, $this->fixture->activeWorkspaceOwnerCount($workspaceInternalId));
    }

    public function testOneStepUpGrantCannotAuthorizeTwoConcurrentRoleMutations(): void
    {
        [$accountInternalId] = $this->fixture->account();
        $action = StepUpAction::AUTHORIZATION_PLATFORM_ROLE_ASSIGN;
        $sessionInternalId = $this->fixture->stepUpGrant($accountInternalId, $action->value);
        $payload = json_encode([
            'operation' => 'step_up_grant',
            'account_id' => $accountInternalId,
            'session_id' => $sessionInternalId,
            'action' => $action->value,
            'now' => '2026-08-28T10:05:00+00:00',
        ], JSON_THROW_ON_ERROR);
        $results = $this->runWorkers(
            dirname(__DIR__, 2) . '/Support/MySql/P2MultiFactorMutationWorker.php',
            [$payload, $payload],
        );
        sort($results);

        self::assertSame([false, true], $results);
        self::assertSame(1, $this->fixture->consumedStepUpGrantCount($accountInternalId, $action->value));
    }

    /**
     * @param list<string> $payloads
     * @return list<bool>
     */
    private function runWorkers(string $worker, array $payloads): array
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
}
