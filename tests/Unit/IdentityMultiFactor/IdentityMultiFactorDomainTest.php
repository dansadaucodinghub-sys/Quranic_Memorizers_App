<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\IdentityMultiFactor;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationMethod;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationTransactionCookieParser;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationTransactionCookieValue;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationTransactionSecret;
use Qmdb\Modules\IdentityMultiFactor\Domain\SessionAuthenticationAssurance;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Shared\Identifier\UuidV7;

final class IdentityMultiFactorDomainTest extends TestCase
{
    public function testAuthenticationTransactionCookieRoundTripsWithoutExposingSecretInDebug(): void
    {
        $value = new AuthenticationTransactionCookieValue(
            UuidV7::generate(),
            AuthenticationTransactionSecret::generate(),
        );
        $parsed = (new AuthenticationTransactionCookieParser())->parse($value->revealForCookie());

        self::assertSame($value->revealForCookie(), $parsed->revealForCookie());
        self::assertSame(['cookie' => '[REDACTED]'], $value->__debugInfo());
    }

    public function testMalformedTransactionCookieIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new AuthenticationTransactionCookieParser())->parse('v1.invalid');
    }

    public function testAssuranceModelDistinguishesPrimaryMultiFactorAndPhishingResistant(): void
    {
        $now = new DateTimeImmutable('2026-08-27T00:00:00Z');
        $primary = new SessionAuthenticationAssurance(
            AuthenticationMethod::PASSWORD,
            null,
            AuthenticationAssuranceLevel::PRIMARY,
            $now,
            null,
        );
        $multiFactor = new SessionAuthenticationAssurance(
            AuthenticationMethod::PASSWORD,
            AuthenticationMethod::TOTP,
            AuthenticationAssuranceLevel::MULTI_FACTOR,
            $now,
            $now,
        );
        $passkey = new SessionAuthenticationAssurance(
            AuthenticationMethod::PASSKEY,
            null,
            AuthenticationAssuranceLevel::PHISHING_RESISTANT,
            $now,
            $now,
        );

        self::assertSame(AuthenticationAssuranceLevel::PRIMARY, $primary->level);
        self::assertSame(AuthenticationAssuranceLevel::MULTI_FACTOR, $multiFactor->level);
        self::assertSame(AuthenticationAssuranceLevel::PHISHING_RESISTANT, $passkey->level);
        self::assertSame(AuthenticationAssuranceLevel::PRIMARY, StepUpAction::MFA_ENROLL_TOTP->requirement());
        self::assertSame(AuthenticationAssuranceLevel::MULTI_FACTOR, StepUpAction::MFA_DISABLE->requirement());
    }

    public function testInconsistentAssuranceIsRejected(): void
    {
        $now = new DateTimeImmutable('2026-08-27T00:00:00Z');
        $this->expectException(\InvalidArgumentException::class);
        new SessionAuthenticationAssurance(
            AuthenticationMethod::PASSWORD,
            null,
            AuthenticationAssuranceLevel::MULTI_FACTOR,
            $now,
            null,
        );
    }
}
