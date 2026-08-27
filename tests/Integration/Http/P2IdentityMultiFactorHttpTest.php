<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Tests\Support\Http\HttpTestFactory;
use Qmdb\Tests\Support\Http\ProductionHttpRuntimeFactory;

final class P2IdentityMultiFactorHttpTest extends TestCase
{
    public function testLoginKeepsPasswordFallbackAndAddsProgressivePasskeyControl(): void
    {
        $response = ProductionHttpRuntimeFactory::create()->handle(HttpTestFactory::request('GET', '/login'));
        $html = (string)$response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('action="/login"', $html);
        self::assertStringContainsString('type="password"', $html);
        self::assertStringContainsString('data-qmdb-passkey-login', $html);
        self::assertStringContainsString('data-options-url="/login/passkey/options"', $html);
        self::assertStringContainsString('data-verify-url="/login/passkey/verify"', $html);
        self::assertStringContainsString(' hidden', $html);
        self::assertNotSame('', $response->getHeaderLine('X-Request-ID'));
    }

    public function testMfaLoginWithoutTransactionRedirectsWithoutIssuingSession(): void
    {
        $response = ProductionHttpRuntimeFactory::create()->handle(HttpTestFactory::request('GET', '/login/mfa'));

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/login', $response->getHeaderLine('Location'));
        self::assertStringNotContainsString('__Host-qmdb_session=', $response->getHeaderLine('Set-Cookie'));
        self::assertNotSame('', $response->getHeaderLine('X-Request-ID'));
    }

    public function testMalformedMfaTransactionCookieIsCleared(): void
    {
        $request = HttpTestFactory::request('GET', '/login/mfa')->withCookieParams([
            'qmdb_auth_tx' => 'not-a-valid-transaction-cookie',
        ]);
        $response = ProductionHttpRuntimeFactory::create()->handle($request);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/login', $response->getHeaderLine('Location'));
        self::assertStringContainsString('qmdb_auth_tx=', $response->getHeaderLine('Set-Cookie'));
        self::assertStringContainsString('Max-Age=0', $response->getHeaderLine('Set-Cookie'));
    }

    /** @return iterable<string, array{string}> */
    public static function protectedPages(): iterable
    {
        yield 'authentication security' => ['/account/security/authentication'];
        yield 'step up' => ['/account/step-up/MFA_ENABLE'];
        yield 'TOTP enrollment' => ['/account/security/mfa/totp/enroll'];
        yield 'passkey registration' => ['/account/security/passkeys/register'];
        yield 'recovery code status' => ['/account/security/mfa/recovery-codes'];
        yield 'MFA disablement' => ['/account/security/mfa/disable'];
    }

    #[DataProvider('protectedPages')]
    public function testAuthenticatorManagementPagesRequireAuthentication(string $path): void
    {
        $response = ProductionHttpRuntimeFactory::create()->handle(HttpTestFactory::request('GET', $path));

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/login', $response->getHeaderLine('Location'));
        self::assertStringNotContainsString('secret', strtolower((string)$response->getBody()));
    }

    public function testPasswordlessPasskeyOptionsRejectMissingCsrfWithoutDatabaseAccess(): void
    {
        $request = HttpTestFactory::request('POST', '/login/passkey/options')
            ->withHeader('Content-Type', 'application/json')
            ->withBody(HttpTestFactory::psr17()->createStream('{}'));
        $response = ProductionHttpRuntimeFactory::create()->handle($request);
        $body = json_decode((string)$response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertIsArray($body);
        self::assertSame(422, $response->getStatusCode());
        self::assertSame('AUTHENTICATION_REQUEST_REJECTED', $body['code'] ?? null);
        self::assertStringContainsString('application/problem+json', $response->getHeaderLine('Content-Type'));
        self::assertNotSame('', $response->getHeaderLine('X-Request-ID'));
    }

    public function testArabicLoginPreservesRtlPasskeyAndPasswordFallbacks(): void
    {
        $request = HttpTestFactory::request('GET', '/login?lang=ar')->withQueryParams(['lang' => 'ar']);
        $response = ProductionHttpRuntimeFactory::create()->handle($request);
        $html = (string)$response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('<html lang="ar" dir="rtl">', $html);
        self::assertStringContainsString('type="password"', $html);
        self::assertStringContainsString('data-qmdb-passkey-login', $html);
    }
}
