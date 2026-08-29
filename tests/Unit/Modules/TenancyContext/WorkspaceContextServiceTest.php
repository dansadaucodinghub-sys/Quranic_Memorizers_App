<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Modules\TenancyContext;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationMethod;
use Qmdb\Modules\IdentityMultiFactor\Domain\SessionAuthenticationAssurance;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\IdentitySessions\Domain\DeviceId;
use Qmdb\Modules\IdentitySessions\Domain\SessionId;
use Qmdb\Modules\IdentitySessions\Domain\SessionStatus;
use Qmdb\Modules\Tenancy\Domain\MembershipStatus;
use Qmdb\Modules\Tenancy\Domain\Value\WorkspaceId;
use Qmdb\Modules\Tenancy\Domain\WorkspaceStatus;
use Qmdb\Modules\TenancyContext\Application\Exception\StaleTenantContextException;
use Qmdb\Modules\TenancyContext\Application\Exception\WorkspaceContextUnavailableException;
use Qmdb\Modules\TenancyContext\Application\SessionTenantContextResolver;
use Qmdb\Modules\TenancyContext\Application\WorkspaceContextClearingCommand;
use Qmdb\Modules\TenancyContext\Application\WorkspaceContextClearingService;
use Qmdb\Modules\TenancyContext\Application\WorkspaceContextSelectionCommand;
use Qmdb\Modules\TenancyContext\Application\WorkspaceContextSelectionService;
use Qmdb\Modules\TenancyContext\Domain\SessionTenantContextState;
use Qmdb\Modules\TenancyContext\Domain\TenantContextVersion;
use Qmdb\Modules\TenancyContext\Domain\WorkspaceContextOption;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Observability\Correlation\CorrelationId;
use Qmdb\Tests\Support\IdentityAccess\FixedIdentityClock;
use Qmdb\Tests\Support\Observability\InMemoryEventLogger;
use Qmdb\Tests\Support\TenancyContext\ImmediateTransactionManager;
use Qmdb\Tests\Support\TenancyContext\InMemorySessionTenantContextRepository;

final class WorkspaceContextServiceTest extends TestCase
{
    private const string NOW = '2026-08-28T13:00:00Z';

    public function testSelectionIsVersionedLoggedAndIdempotentWhileUnavailableAndStaleFailClosed(): void
    {
        $account = $this->account();
        $option = $this->option();
        $repository = new InMemorySessionTenantContextRepository($this->emptyState($account), [$option]);
        $logger = new InMemoryEventLogger();
        $service = new WorkspaceContextSelectionService(
            $repository,
            new ImmediateTransactionManager(),
            new FixedIdentityClock(new DateTimeImmutable(self::NOW)),
            $logger,
        );
        $result = $service->execute(new WorkspaceContextSelectionCommand(
            $account,
            $option->workspaceId,
            new TenantContextVersion(1),
            new CorrelationId(str_repeat('a', 32)),
        ));
        self::assertTrue($result->changed);
        self::assertSame(2, $result->context->version->value);
        self::assertSame('tenancy.context.selected', $logger->records()[0]['event']);
        self::assertSame(str_repeat('a', 32), $logger->records()[0]['context']['request_id']);

        $idempotent = $service->execute(new WorkspaceContextSelectionCommand(
            $account,
            $option->workspaceId,
            new TenantContextVersion(2),
        ));
        self::assertFalse($idempotent->changed);
        self::assertSame(2, $idempotent->context->version->value);
        self::assertCount(1, $logger->records());

        try {
            $service->select($account, $option->workspaceId, new TenantContextVersion(1));
            self::fail('A stale expected version must fail.');
        } catch (StaleTenantContextException) {
            self::assertSame(2, $repository->currentState->version->value);
        }
        try {
            $service->select($account, WorkspaceId::generate(), new TenantContextVersion(2));
            self::fail('An unavailable workspace must fail without enumeration.');
        } catch (WorkspaceContextUnavailableException $exception) {
            self::assertSame('TENANT_CONTEXT_UNAVAILABLE', $exception->safeCode());
        }
    }

    public function testClearingIsVersionedLoggedAndIdempotentWithoutEndingTheSession(): void
    {
        $account = $this->account();
        $option = $this->option();
        $repository = new InMemorySessionTenantContextRepository($this->emptyState($account), [$option]);
        $transactions = new ImmediateTransactionManager();
        $clock = new FixedIdentityClock(new DateTimeImmutable(self::NOW));
        (new WorkspaceContextSelectionService($repository, $transactions, $clock))->select(
            $account,
            $option->workspaceId,
            new TenantContextVersion(1),
        );
        $logger = new InMemoryEventLogger();
        $clearing = new WorkspaceContextClearingService($repository, $transactions, $clock, $logger);
        $cleared = $clearing->execute(new WorkspaceContextClearingCommand(
            $account,
            new TenantContextVersion(2),
            new CorrelationId(str_repeat('b', 32)),
        ));
        self::assertTrue($cleared->changed);
        self::assertSame(3, $cleared->version->value);
        self::assertSame(SessionStatus::ACTIVE, $repository->currentState->sessionStatus);
        self::assertFalse($repository->currentState->hasStoredSelection);
        self::assertSame('tenancy.context.cleared', $logger->records()[0]['event']);

        $again = $clearing->execute(new WorkspaceContextClearingCommand(
            $account,
            new TenantContextVersion(3),
        ));
        self::assertFalse($again->changed);
        self::assertSame(3, $again->version->value);
        self::assertCount(1, $logger->records());
    }

    public function testResolverClearsAnInvalidStoredSelectionAndNeverChoosesAReplacement(): void
    {
        $account = $this->account();
        $option = $this->option();
        $repository = new InMemorySessionTenantContextRepository(new SessionTenantContextState(
            $account->sessionInternalId,
            $account->accountInternalId,
            $option->workspaceInternalId,
            $option->membershipInternalId,
            new TenantContextVersion(4),
            new DateTimeImmutable(self::NOW),
            SessionStatus::ACTIVE,
            $account->sessionVersion,
            true,
            null,
        ), [$option]);
        $logger = new InMemoryEventLogger();
        $resolution = (new SessionTenantContextResolver(
            $repository,
            new ImmediateTransactionManager(),
            new FixedIdentityClock(new DateTimeImmutable(self::NOW)),
            $logger,
        ))->resolve($account);

        self::assertNull($resolution->context);
        self::assertTrue($resolution->invalidSelectionCleared);
        self::assertSame(5, $resolution->version->value);
        self::assertFalse($repository->currentState->hasStoredSelection);
        self::assertSame('tenancy.context.invalidated', $logger->records()[0]['event']);
    }

    private function emptyState(AuthenticatedAccountContext $account): SessionTenantContextState
    {
        return new SessionTenantContextState(
            $account->sessionInternalId,
            $account->accountInternalId,
            null,
            null,
            new TenantContextVersion(1),
            null,
            SessionStatus::ACTIVE,
            $account->sessionVersion,
            false,
            null,
        );
    }

    private function option(): WorkspaceContextOption
    {
        return new WorkspaceContextOption(
            41,
            WorkspaceId::generate(),
            'Verified Workspace',
            WorkspaceStatus::ACTIVE,
            1,
            43,
            UuidV7::generate(),
            MembershipStatus::ACTIVE,
            1,
            new DateTimeImmutable(self::NOW),
        );
    }

    private function account(): AuthenticatedAccountContext
    {
        $now = new DateTimeImmutable(self::NOW);

        return new AuthenticatedAccountContext(
            17,
            AccountId::generate(),
            19,
            SessionId::generate(),
            23,
            DeviceId::generate(),
            $now,
            2,
            new SessionAuthenticationAssurance(
                AuthenticationMethod::PASSWORD,
                null,
                AuthenticationAssuranceLevel::PRIMARY,
                $now,
                null,
            ),
        );
    }
}
