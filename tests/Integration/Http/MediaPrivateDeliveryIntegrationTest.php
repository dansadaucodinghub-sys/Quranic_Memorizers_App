<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\Http;

use DateTimeImmutable;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\IdentityMultiFactor\Domain\{AuthenticationAssuranceLevel, AuthenticationMethod, SessionAuthenticationAssurance};
use Qmdb\Modules\IdentitySessions\Application\{AuthenticatedAccountContext, AuthenticationAttributes};
use Qmdb\Modules\IdentitySessions\Domain\{DeviceId, SessionId};
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\MediaCatalog\Application\MediaEvidenceRepository;
use Qmdb\Modules\MediaDelivery\Interface\Http\PrivateMediaDeliveryController;
use Qmdb\Modules\MediaIngestion\Application\MediaBlobStore;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequirementGuard;
use Qmdb\Modules\SecurityAuthorization\Application\Exception\AuthorizationDeniedException;
use Qmdb\Modules\Tenancy\Domain\{MembershipStatus, WorkspaceStatus};
use Qmdb\Modules\Tenancy\Domain\Value\WorkspaceId;
use Qmdb\Modules\TenancyContext\Application\{TenantContextAttributes, TenantContextFreshnessValidator, TenantContextRequiredGuard};
use Qmdb\Modules\TenancyContext\Domain\{AccountWorkspaceTenantContext, ResolvedWorkspaceMembershipIdentity, TenantContextVersion};
use Qmdb\Shared\Http\Message\JsonResponseFactory;
use Qmdb\Shared\Http\Routing\RouteAttributes;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Presentation\Response\FragmentRequestDetector;

final class MediaPrivateDeliveryIntegrationTest extends TestCase
{
    /** @return iterable<string,array{string,int,string}> */
    public static function ranges(): iterable
    {
        yield 'ordinary no-JavaScript request' => ['',200,'0123456789'];
        yield 'explicit range' => ['bytes=2-5',206,'2345'];
        yield 'suffix range' => ['bytes=-3',206,'789'];
        yield 'open range' => ['bytes=7-',206,'789'];
        yield 'bounded large end' => ['bytes=8-999',206,'89'];
        yield 'unsatisfiable' => ['bytes=10-',416,''];
        yield 'inverted' => ['bytes=5-2',416,''];
        yield 'multipart rejected' => ['bytes=0-1,3-4',416,''];
    }

    #[DataProvider('ranges')]
    public function testPrivateRangesWithoutAJavaScriptHeader(string $range, int $status, string $body): void
    {
        [$controller,$request] = $this->fixture();
        $response = $controller->handle($request->withHeader('Range', $range));
        self::assertSame($status, $response->getStatusCode());
        self::assertSame($body, (string)$response->getBody());
        self::assertSame('private, no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
    }

    public function testIfRangeMismatchReturnsTheWholeCurrentRepresentation(): void
    {
        [$controller,$request] = $this->fixture();
        $response = $controller->handle($request->withHeader('Range', 'bytes=2-3')->withHeader('If-Range', '"old"'));
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('0123456789', (string)$response->getBody());
    }

    public function testCurrentEtagStillChecksRevocablePolicyAndStorage(): void
    {
        [$controller,$request] = $this->fixture();
        $response = $controller->handle($request->withHeader('If-None-Match', '"' . hash('sha256', '0123456789') . '"'));
        self::assertSame(304, $response->getStatusCode());
        self::assertSame('', (string)$response->getBody());
    }

    public function testRevokedOrHeldMediaIsNotReturnedEvenWithACachedEtag(): void
    {
        [$controller,$request] = $this->fixture('revoked');
        self::assertSame(404, $controller->handle($request->withHeader('If-None-Match', '"' . hash('sha256', '0123456789') . '"'))->getStatusCode());
    }

    public function testAuthorizationFailureNeverReadsTheAssetOrStorage(): void
    {
        [$controller,$request] = $this->fixture('denied');
        self::assertSame(403, $controller->handle($request)->getStatusCode());
    }

    public function testCorruptStorageCannotReturnANotModifiedResponse(): void
    {
        [$controller,$request] = $this->fixture('corrupt');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Private media integrity verification failed.');
        $controller->handle($request->withHeader('If-None-Match', '"' . hash('sha256', '0123456789') . '"'));
    }

    /** @return array{PrivateMediaDeliveryController,ServerRequest} */
    private function fixture(string $scenario = 'valid'): array
    {
        $now = new DateTimeImmutable('2026-09-17T12:00:00Z');
        $actor = new AuthenticatedAccountContext(8, AccountId::generate(), 12, SessionId::generate(), 11, DeviceId::generate(), $now, 1, new SessionAuthenticationAssurance(AuthenticationMethod::PASSKEY, null, AuthenticationAssuranceLevel::PHISHING_RESISTANT, $now, $now));
        $tenant = AccountWorkspaceTenantContext::trusted(8, $actor->accountId, 12, $actor->sessionId, 4, WorkspaceId::generate(), WorkspaceStatus::ACTIVE, 1, ResolvedWorkspaceMembershipIdentity::trusted(13, UuidV7::generate(), 4, 8, MembershipStatus::ACTIVE, 1), 'Synthetic workspace', new TenantContextVersion(1), $now);
        $id = UuidV7::generate();
        $responses = new Psr17Factory();
        $authorization = $this->createMock(AuthorizationRequirementGuard::class);
        $authorization->expects(self::once())->method('requireAllowed');
        if ($scenario === 'denied') {
            $authorization->method('requireAllowed')->willThrowException(new AuthorizationDeniedException());
        }
        $assets = $this->createMock(MediaEvidenceRepository::class);
        $assets->expects($scenario === 'denied' ? self::never() : self::once())->method('findDeliverable')->with(4, $id)->willReturn($scenario === 'revoked' ? null : ['storage_key' => 'variants/test.mp3','mime_type' => 'audio/mpeg','byte_size' => 10,'sha256' => hash('sha256', '0123456789', true)]);
        $storage = $this->createMock(MediaBlobStore::class);
        $storage->expects(in_array($scenario, ['denied','revoked'], true) ? self::never() : self::once())->method('get')->with('variants/test.mp3')->willReturn($scenario === 'corrupt' ? '012345678X' : '0123456789');
        $controller = new PrivateMediaDeliveryController(new AuthenticatedRequestGuard(new FragmentRequestDetector(), new JsonResponseFactory($responses, $responses), $responses), new TenantContextRequiredGuard(new TenantContextFreshnessValidator()), $authorization, $assets, $storage, $responses);
        $request = (new ServerRequest('GET', '/workspace/media/' . $id->toString() . '/content'))->withAttribute(AuthenticationAttributes::ACCOUNT, $actor)->withAttribute(TenantContextAttributes::CONTEXT, $tenant)->withAttribute(RouteAttributes::PARAMETERS, ['assetId' => $id->toString()]);
        return [$controller,$request];
    }
}
