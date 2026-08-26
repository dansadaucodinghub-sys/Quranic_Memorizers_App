<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Modules\IdentitySessions;

use InvalidArgumentException;
use LogicException;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Modules\IdentitySessions\Application\AuthenticationCookieResponseDecorator;
use Qmdb\Modules\IdentitySessions\Application\DeviceCookieFactory;
use Qmdb\Modules\IdentitySessions\Application\SessionCookieFactory;
use Qmdb\Modules\IdentitySessions\Configuration\IdentitySessionConfiguration;
use Qmdb\Modules\IdentitySessions\Domain\DeviceCookieParser;
use Qmdb\Modules\IdentitySessions\Domain\DeviceCookieValue;
use Qmdb\Modules\IdentitySessions\Domain\DeviceId;
use Qmdb\Modules\IdentitySessions\Domain\DeviceTokenSecret;
use Qmdb\Modules\IdentitySessions\Domain\SessionCookieParser;
use Qmdb\Modules\IdentitySessions\Domain\SessionCookieValue;
use Qmdb\Modules\IdentitySessions\Domain\SessionId;
use Qmdb\Modules\IdentitySessions\Domain\SessionTokenSecret;

final class IdentitySessionSecurityTest extends TestCase
{
    public function testSessionTokenUsesTwoHundredFiftySixBitsAndIsRedacted(): void
    {
        $secret = SessionTokenSecret::generate();
        $raw = $secret->revealForCookie();

        self::assertMatchesRegularExpression('/\A[A-Za-z0-9_-]{43}\z/', $raw);
        self::assertSame(32, strlen($secret->hash()->toBinary()));
        self::assertTrue($secret->hash()->matches($secret));
        self::assertSame(['value' => '[REDACTED]'], $secret->__debugInfo());
        $this->expectException(LogicException::class);
        serialize($secret);
    }

    public function testDeviceTokenIsIndependentAndRedacted(): void
    {
        $session = SessionTokenSecret::generate();
        $device = DeviceTokenSecret::generate();

        self::assertNotSame($session->revealForCookie(), $device->revealForCookie());
        self::assertTrue($device->hash()->matches($device));
        self::assertSame(['value' => '[REDACTED]'], $device->__debugInfo());
    }

    public function testCookieParsersRoundTripOnlyCanonicalV1Values(): void
    {
        $session = new SessionCookieValue(SessionId::generate(), SessionTokenSecret::generate());
        $device = new DeviceCookieValue(DeviceId::generate(), DeviceTokenSecret::generate());

        self::assertSame(
            $session->sessionId->toString(),
            (new SessionCookieParser())->parse($session->revealForCookie())->sessionId->toString(),
        );
        self::assertSame(
            $device->deviceId->toString(),
            (new DeviceCookieParser())->parse($device->revealForCookie())->deviceId->toString(),
        );
    }

    /** @return iterable<string, array{string}> */
    public static function malformedCookies(): iterable
    {
        yield 'unknown version' => ['v2.invalid.invalid'];
        yield 'too few segments' => ['v1.invalid'];
        yield 'whitespace' => ["v1. invalid.invalid"];
        yield 'control' => ["v1.invalid.\0invalid"];
        yield 'excessive' => [str_repeat('x', 129)];
    }

    #[DataProvider('malformedCookies')]
    public function testSessionCookieParserRejectsMalformedValues(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new SessionCookieParser())->parse($value);
    }

    public function testLocalAndProductionCookiePoliciesAreExact(): void
    {
        $local = $this->configuration(false);
        $production = $this->configuration(true);
        $session = new SessionCookieValue(SessionId::generate(), SessionTokenSecret::generate());
        $device = new DeviceCookieValue(DeviceId::generate(), DeviceTokenSecret::generate());

        $localSession = (new SessionCookieFactory($local))->issue($session)->revealForResponse();
        self::assertStringStartsWith('qmdb_session=', $localSession);
        self::assertStringContainsString('; HttpOnly; SameSite=Lax', $localSession);
        self::assertStringNotContainsString('Max-Age', $localSession);
        self::assertStringNotContainsString('Domain=', $localSession);
        $productionSession = (new SessionCookieFactory($production))->issue($session)->revealForResponse();
        self::assertStringStartsWith('__Host-qmdb_session=', $productionSession);
        self::assertStringContainsString('; Secure', $productionSession);
        $productionDevice = (new DeviceCookieFactory($production))->issue($device)->revealForResponse();
        self::assertStringStartsWith('__Host-qmdb_device=', $productionDevice);
        self::assertStringContainsString('Max-Age=31536000', $productionDevice);
    }

    public function testCookieDecoratorPreservesSeparateSetCookieHeaders(): void
    {
        $configuration = $this->configuration(false);
        $session = (new SessionCookieFactory($configuration))->issue(
            new SessionCookieValue(SessionId::generate(), SessionTokenSecret::generate()),
        );
        $device = (new DeviceCookieFactory($configuration))->issue(
            new DeviceCookieValue(DeviceId::generate(), DeviceTokenSecret::generate()),
        );

        $response = (new AuthenticationCookieResponseDecorator())->apply(new Response(), [$session, $device]);
        self::assertCount(2, $response->getHeader('Set-Cookie'));
    }

    public function testConfigurationRejectsUnsafeLifetimes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new IdentitySessionConfiguration(false, 299, 43200, 900, 30, 60, 10, 31536000);
    }

    private function configuration(bool $productionLike): IdentitySessionConfiguration
    {
        return new IdentitySessionConfiguration(
            $productionLike,
            1800,
            43200,
            900,
            30,
            60,
            10,
            31536000,
        );
    }
}
