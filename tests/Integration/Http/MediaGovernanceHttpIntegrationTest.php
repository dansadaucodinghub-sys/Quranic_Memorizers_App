<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\Http;

use DateTimeImmutable;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Bootstrap\ApplicationFactory;
use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\IdentityAccess\Interface\Http\{IdentityAccessView, IdentityCsrf};
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\{IdentityFingerprint, IdentityFingerprintGenerator};
use Qmdb\Modules\IdentityAccess\Security\RateLimit\{IdentityRateLimiter, IdentityRateLimitDecision};
use Qmdb\Modules\IdentityMultiFactor\Application\StepUpGuard;
use Qmdb\Modules\IdentityMultiFactor\Domain\{AuthenticationAssuranceLevel, AuthenticationMethod, SessionAuthenticationAssurance, StepUpAction, StepUpGrant, StepUpGrantStatus};
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\StepUpGrantRepository;
use Qmdb\Modules\IdentitySessions\Application\{AuthenticatedAccountContext, AuthenticationAttributes};
use Qmdb\Modules\IdentitySessions\Domain\{DeviceId, SessionId};
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\MediaModeration\Application\{MediaGovernanceRepository, MediaGovernanceService};
use Qmdb\Modules\MediaModeration\Domain\{MediaGovernanceAction, MediaGovernanceRecord};
use Qmdb\Modules\MediaModeration\Interface\Http\MediaGovernanceController;
use Qmdb\Modules\SecurityAudit\Application\{SecurityAuditRecorder, SecurityAuditEventAppender, SecurityAuditAppendResult};
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequirementGuard;
use Qmdb\Modules\SecurityAuthorization\Application\Exception\AuthorizationDeniedException;
use Qmdb\Modules\Tenancy\Domain\{MembershipStatus, WorkspaceStatus};
use Qmdb\Modules\Tenancy\Domain\Value\WorkspaceId;
use Qmdb\Modules\TenancyContext\Application\{TenantContextAttributes, TenantContextRequiredGuard};
use Qmdb\Modules\TenancyContext\Domain\{AccountWorkspaceTenantContext, ResolvedWorkspaceMembershipIdentity, TenantContextVersion};
use Qmdb\Shared\Configuration\ApplicationConfigurationFactory;
use Qmdb\Shared\Configuration\Infrastructure\DotenvEnvironmentLoader;
use Qmdb\Shared\Database\Transaction\{TransactionManager, TransactionOptions};
use Qmdb\Shared\DependencyInjection\CompiledContainer;
use Qmdb\Shared\Http\Request\RequestContextAttributes;
use Qmdb\Shared\Http\Routing\RouteAttributes;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Localization\{Locale, LocaleContext};
use Qmdb\Shared\Presentation\Security\CspNonce;
use Qmdb\Shared\Time\Clock;

/** Real controller/CSRF/Step-Up/presentation components; persistence is verified separately on MySQL. */
final class MediaGovernanceHttpIntegrationTest extends TestCase
{
    private static ?CompiledContainer $container = null;

    /** @return iterable<string,array{string,string,int,bool}> */
    public static function submissions(): iterable
    {
        foreach (MediaGovernanceAction::cases() as $action) {
            yield $action->value => [$action->value,'valid',303,false];
            yield $action->value . ':fragment' => [$action->value,'valid',200,true];
        }
        yield 'invalid CSRF' => ['approve','csrf',403,false];
        yield 'invalid CSRF fragment' => ['approve','csrf',403,true];
        yield 'foreign origin' => ['approve','origin',403,false];
        yield 'conflicting CSRF' => ['approve','csrf_conflict',422,true];
        yield 'conflicting idempotency' => ['approve','idempotency',422,true];
        yield 'workspace switched' => ['approve','workspace',409,false];
        yield 'stale aggregate version' => ['approve','version',409,false];
        yield 'missing action grant' => ['approve','step_up',409,false];
        yield 'denied permission' => ['approve','permission',403,false];
        yield 'rate limited form' => ['approve','rate_limit',429,false];
        yield 'rate limited fragment' => ['approve','rate_limit',429,true];
    }

    #[DataProvider('submissions')]
    public function testProtectedFormSubmission(string $actionCode, string $scenario, int $expected, bool $fragment): void
    {
        $action = MediaGovernanceAction::from($actionCode);
        $services = $this->services();
        $now = new DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $actor = new AuthenticatedAccountContext(8, AccountId::generate(), 12, SessionId::generate(), 11, DeviceId::generate(), $now, 1, new SessionAuthenticationAssurance(AuthenticationMethod::PASSKEY, null, AuthenticationAssuranceLevel::PHISHING_RESISTANT, $now, $now));
        $tenant = AccountWorkspaceTenantContext::trusted(8, $actor->accountId, 12, $actor->sessionId, 4, WorkspaceId::generate(), WorkspaceStatus::ACTIVE, 1, ResolvedWorkspaceMembershipIdentity::trusted(13, UuidV7::generate(), 4, 8, MembershipStatus::ACTIVE, 1), 'Synthetic workspace', new TenantContextVersion(1), $now);
        $assetId = UuidV7::generate();
        $status = $action === MediaGovernanceAction::ARCHIVE ? 'WITHDRAWN' : 'PENDING_MODERATION';
        $record = new MediaGovernanceRecord(5, $assetId->toString(), 4, $action === MediaGovernanceAction::WITHDRAW_CONSENT ? 8 : 7, $status, 4, true, true, $action === MediaGovernanceAction::RELEASE_HOLD, true, true);
        $repository = $this->createMock(MediaGovernanceRepository::class);
        $repository->method('find')->willReturn($record);
        $repository->method('replay')->willReturn(null);
        $repository->expects($scenario === 'valid' ? self::once() : self::never())->method('apply');
        $repository->expects($scenario === 'valid' ? self::once() : self::never())->method('record');
        $authorization = $this->createStub(AuthorizationRequirementGuard::class);
        if ($scenario === 'permission') {
            $authorization->method('requireAllowed')->willThrowException(new AuthorizationDeniedException());
        }
        $clock = $this->createStub(Clock::class);
        $clock->method('now')->willReturn($now);
        $grants = $this->createStub(StepUpGrantRepository::class);
        $grantAction = $action->stepUp() ?? StepUpAction::MEDIA_APPROVE;
        $grants->method('findActiveGrant')->willReturn($scenario === 'step_up' ? null : new StepUpGrant(1, UuidV7::generate()->toString(), 8, 12, $grantAction, AuthenticationAssuranceLevel::PHISHING_RESISTANT, StepUpGrantStatus::ACTIVE, $now, $now->modify('+5 minutes'), null, null, 1));
        $grants->method('consumeGrant')->willReturn(true);
        $limits = $this->createStub(IdentityRateLimiter::class);
        $limits->method('consume')->willReturn($scenario === 'rate_limit' ? IdentityRateLimitDecision::throttled(60) : IdentityRateLimitDecision::allowed());
        $fingerprints = $this->createStub(IdentityFingerprintGenerator::class);
        $fingerprints->method('generate')->willReturnCallback(static fn(string $domain, string $value): IdentityFingerprint => new IdentityFingerprint(hash('sha256', $domain . $value, true)));
        $transactions = $this->createStub(TransactionManager::class);
        $transactions->method('transactional')->willReturnCallback(static fn(callable $operation, ?TransactionOptions $options = null): mixed => $operation());
        $audit = $this->createMock(SecurityAuditRecorder::class);
        $audit->expects($scenario === 'valid' ? self::once() : self::never())->method('append')->willReturn(new SecurityAuditAppendResult(UuidV7::generate()->toString(), UuidV7::generate()->toString(), 1));
        $service = new MediaGovernanceService($repository, $authorization, new StepUpGuard($grants, $clock), $limits, $fingerprints, new SecurityAuditEventAppender($audit), $transactions, $clock);
        $authentication = $services->get(AuthenticatedRequestGuard::class);
        $tenantGuard = $services->get(TenantContextRequiredGuard::class);
        $csrf = $services->get(IdentityCsrf::class);
        $views = $services->get(IdentityAccessView::class);
        self::assertInstanceOf(AuthenticatedRequestGuard::class, $authentication);
        self::assertInstanceOf(TenantContextRequiredGuard::class, $tenantGuard);
        self::assertInstanceOf(IdentityCsrf::class, $csrf);
        self::assertInstanceOf(IdentityAccessView::class, $views);
        $controller = new MediaGovernanceController($authentication, $tenantGuard, $authorization, $csrf, $service, $repository, $views);
        $path = '/workspace/media/' . $assetId->toString() . '/' . $actionCode;
        $request = (new ServerRequest('POST', 'http://127.0.0.1:8080' . $path))
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withHeader('Origin', $scenario === 'origin' ? 'https://untrusted.example' : 'http://127.0.0.1:8080')
            ->withAttribute(AuthenticationAttributes::ACCOUNT, $actor)
            ->withAttribute(TenantContextAttributes::CONTEXT, $tenant)
            ->withAttribute(RouteAttributes::PARAMETERS, ['assetId' => $assetId->toString()])
            ->withAttribute(RequestContextAttributes::LOCALE, new LocaleContext(new Locale('en')))
            ->withAttribute(RequestContextAttributes::CSP_NONCE, new CspNonce(str_repeat('a', 32)));
        if ($fragment) {
            $request = $request->withHeader('Accept', 'text/vnd.qmdb.fragment+html');
        }
        $issued = $csrf->issue($request, $action->csrf());
        $request = $request->withCookieParams(['qmdb_csrf' => $issued['cookie']->nonce->value()]);
        $body = ['csrf_token' => $scenario === 'csrf' ? 'invalid' : $issued['token'],'submission_id' => UuidV7::generate()->toString(),'version' => $scenario === 'version' ? '3' : '4','reason_code' => 'INDEPENDENT_REVIEW','hold_code' => 'GOVERNANCE','workspace_id' => $scenario === 'workspace' ? UuidV7::generate()->toString() : $tenant->workspacePublicId(),'tenant_context_version' => '1'];
        if ($scenario === 'csrf_conflict') {
            $request = $request->withHeader('X-QMDB-CSRF', 'conflicting');
        }
        if ($scenario === 'idempotency') {
            $request = $request->withHeader('Idempotency-Key', UuidV7::generate()->toString());
        }
        if ($action === MediaGovernanceAction::GRANT_CONSENT) {
            $body += ['evidence_reference' => UuidV7::generate()->toString(), 'evidence_sha256' => hash('sha256', 'synthetic consent bundle'), 'participants' => '2', 'participant_consents' => '2', 'minors' => '1', 'guardian_consents' => '1', 'rights_verified' => '1', 'organization_authority_verified' => '1'];
        }
        $response = $controller->handle($request->withParsedBody($body));
        self::assertSame($expected, $response->getStatusCode());
        if ($expected === 429) {
            self::assertSame('60', $response->getHeaderLine('Retry-After'));
        }
        self::assertStringContainsString('no-store', $response->getHeaderLine('Cache-Control'));
        if ($expected === 303) {
            self::assertSame($path . '?saved=1', $response->getHeaderLine('Location'));
        } elseif ($expected === 200) {
            self::assertSame('1', $response->getHeaderLine('X-QMDB-Fragment'));
            self::assertStringContainsString('text/vnd.qmdb.fragment+html', $response->getHeaderLine('Content-Type'));
            self::assertSame('', $response->getHeaderLine('Location'));
            self::assertStringContainsString('data-qmdb-completion-heading', (string)$response->getBody());
        } else {
            self::assertStringContainsString('role="alert"', (string)$response->getBody());
        }
    }

    private function services(): CompiledContainer
    {
        if (self::$container === null) {
            $factory = new ApplicationFactory(dirname(__DIR__, 3), new DotenvEnvironmentLoader([
                'APP_ENV' => 'test','APP_DEBUG' => 'false','APP_TIMEZONE' => 'UTC','APP_LOG_LEVEL' => 'emergency','APP_PUBLIC_BASE_URL' => 'http://127.0.0.1:8080',
                'AUTH_CSRF_SIGNING_KEY' => bin2hex(random_bytes(32)),'AUTH_IDENTITY_HMAC_KEY' => bin2hex(random_bytes(32)),
                'AUTH_CONTACT_ENCRYPTION_KEY' => base64_encode(random_bytes(32)),'AUTH_MFA_ENCRYPTION_KEY' => base64_encode(random_bytes(32)),'MAILER_DSN' => 'null://null','MAIL_FROM_ADDRESS' => 'no-reply@example.test',
            ]), new ApplicationConfigurationFactory());
            // Composition is kept private in production; this test uses the real boundary components.
            $container = (new \ReflectionMethod(ApplicationFactory::class, 'compose'))->invoke($factory, '8.5.0', ['json','mbstring']);
            self::assertInstanceOf(CompiledContainer::class, $container);
            self::$container = $container;
        }
        return self::$container;
    }
}
