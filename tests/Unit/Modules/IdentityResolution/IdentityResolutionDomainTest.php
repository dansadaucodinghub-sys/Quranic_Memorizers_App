<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Modules\IdentityResolution;

use PHPUnit\Framework\TestCase;
use DateTimeImmutable;
use DateTimeZone;
use Qmdb\Modules\IdentityResolution\Application\ProfileClaimPairingHasher;
use Qmdb\Modules\IdentityResolution\Application\ProfileClaimEligibility;
use Qmdb\Modules\IdentityResolution\Application\PersonDuplicateResolutionPlan;
use Qmdb\Modules\IdentityResolution\Configuration\IdentityResolutionConfiguration;
use Qmdb\Modules\IdentityResolution\Configuration\IdentityResolutionConfigurationFactory;
use Qmdb\Modules\IdentityResolution\Domain\PersonDuplicateCaseStatus;
use Qmdb\Modules\IdentityResolution\Domain\PersonDuplicateConsentAuthorityType;
use Qmdb\Modules\IdentityResolution\Domain\PersonDuplicateConsentDecision;
use Qmdb\Modules\IdentityResolution\Domain\PersonDuplicateResolutionOutcome;
use Qmdb\Modules\IdentityResolution\Domain\ProfileClaimAuthorizationType;
use Qmdb\Modules\IdentityResolution\Domain\ProfileClaimPairingCode;
use Qmdb\Modules\IdentityResolution\Domain\ProfileClaimPairingStatus;
use Qmdb\Modules\IdentityResolution\Domain\ProfileClaimStatus;
use Qmdb\Modules\IdentityResolution\Domain\ProfileVerificationAssertionStatus;
use Qmdb\Modules\IdentityResolution\Domain\ProfileVerificationAssertionType;
use Qmdb\Modules\IdentityResolution\Infrastructure\Security\SecureProfileClaimPairingCodeGenerator;
use Qmdb\Shared\Configuration\ApplicationConfigurationFactory;
use Qmdb\Shared\Configuration\ConfigurationSource;
use Qmdb\Shared\Configuration\EnvironmentVariables;

final class IdentityResolutionDomainTest extends TestCase
{
    public function testPairingCodeFormatParsesExactlyAndRejectsMalformedValues(): void
    {
        $code = ProfileClaimPairingCode::issue('A1B2C3D4E5F6', '0123456789abcdefghij_-AB');

        self::assertSame('QMPC-A1B2C3D4E5F6-0123456789abcdefghij_-AB', $code->displayOnce());
        self::assertSame($code->selector, ProfileClaimPairingCode::parse($code->displayOnce())?->selector);
        self::assertSame($code->secret, ProfileClaimPairingCode::parse('  ' . $code->displayOnce() . '  ')?->secret);
        self::assertNull(ProfileClaimPairingCode::parse('QMP-A1B2C3D4E5F6-0123456789abcdefghij_-AB'));
        self::assertNull(ProfileClaimPairingCode::parse('QMPC-A1B2-0123456789abcdefghij_-AB'));
        self::assertNull(ProfileClaimPairingCode::parse('QMPC-A1B2C3D4E5F6-too-short'));
        $this->expectException(\InvalidArgumentException::class);
        ProfileClaimPairingCode::issue('lowercase1234', '0123456789abcdefghij_-AB');
    }

    public function testSecureGeneratorProducesValidDistinct128BitPairingsAndRejectsWeakEntropy(): void
    {
        $generator = new SecureProfileClaimPairingCodeGenerator();
        $first = $generator->generate(128);
        $second = $generator->generate(128);

        self::assertMatchesRegularExpression('/\A[A-Z0-9]{12}\z/', $first->selector);
        self::assertMatchesRegularExpression('/\A[A-Za-z0-9_-]{22}\z/', $first->secret);
        self::assertNotSame($first->displayOnce(), $second->displayOnce());
        self::assertNotNull(ProfileClaimPairingCode::parse($first->displayOnce()));

        $this->expectException(\InvalidArgumentException::class);
        $generator->generate(120);
    }

    public function testVersionedHmacIsFixedLengthKeyBoundAndNeverEqualsThePlaintextSecret(): void
    {
        $code = ProfileClaimPairingCode::issue('A1B2C3D4E5F6', '0123456789abcdefghij_-AB');
        $hasher = new ProfileClaimPairingHasher($this->configuration(str_repeat('k', 32), 7));
        $hash = $hasher->hash($code);

        self::assertSame(32, strlen($hash));
        self::assertNotSame($code->secret, $hash);
        self::assertTrue($hasher->matches($code, $hash));
        self::assertFalse($hasher->matches(ProfileClaimPairingCode::issue('A1B2C3D4E5F6', '0123456789abcdefghij_-AC'), $hash));
        self::assertFalse((new ProfileClaimPairingHasher($this->configuration(str_repeat('z', 32), 7)))->matches($code, $hash));
        self::assertNotSame($hash, (new ProfileClaimPairingHasher($this->configuration(str_repeat('k', 32), 8)))->hash($code));
    }

    public function testConfigurationAppliesStrongDefaultsAndRejectsWeakOrReusedKeyMaterial(): void
    {
        $variables = $this->variables(['AUTH_PROFILE_CLAIM_PAIRING_HMAC_KEY' => str_repeat('p', 32)]);
        $application = (new ApplicationConfigurationFactory())->create($variables, ConfigurationSource::PROCESS);
        $configuration = (new IdentityResolutionConfigurationFactory())->create($variables, $application);

        self::assertSame(128, $configuration->pairingEntropyBits);
        self::assertSame(1_800, $configuration->pairingTtlSeconds);
        self::assertSame(10, $configuration->pairingMaximumAttempts);
        self::assertGreaterThanOrEqual($configuration->pairingTtlSeconds, $configuration->claimTtlSeconds);
        self::assertSame(1, $configuration->pairingHmacKeyVersion);

        $this->expectException(\InvalidArgumentException::class);
        (new IdentityResolutionConfigurationFactory())->create(
            $this->variables([
                'AUTH_PROFILE_CLAIM_PAIRING_HMAC_KEY' => str_repeat('a', 32),
                'AUTH_SECURITY_AUDIT_HMAC_KEY' => str_repeat('a', 32),
            ]),
            $application,
        );
    }

    public function testConfigurationRejectsInvalidKeyVersionsAndClaimTtlShorterThanPairingTtl(): void
    {
        $application = (new ApplicationConfigurationFactory())->create($this->variables(), ConfigurationSource::PROCESS);

        try {
            (new IdentityResolutionConfigurationFactory())->create($this->variables([
                'AUTH_PROFILE_CLAIM_PAIRING_HMAC_KEY_VERSION' => '0',
            ]), $application);
            self::fail('An unknown pairing HMAC key version was accepted.');
        } catch (\InvalidArgumentException) {
            self::addToAssertionCount(1);
        }
        $this->expectException(\InvalidArgumentException::class);
        (new IdentityResolutionConfigurationFactory())->create($this->variables([
            'PERSON_PROFILE_CLAIM_PAIRING_TTL_SECONDS' => '3600',
            'PERSON_PROFILE_CLAIM_TTL_SECONDS' => '1800',
        ]), $application);
    }

    public function testLifecycleEnumsAllowOnlyExplicitTerminalTransitions(): void
    {
        self::assertTrue(ProfileClaimPairingStatus::ACTIVE->permits(ProfileClaimPairingStatus::CONSUMED));
        self::assertTrue(ProfileClaimPairingStatus::ACTIVE->permits(ProfileClaimPairingStatus::REVOKED));
        self::assertFalse(ProfileClaimPairingStatus::CONSUMED->permits(ProfileClaimPairingStatus::REVOKED));
        self::assertTrue(ProfileClaimStatus::PENDING_ACCEPTANCE->permits(ProfileClaimStatus::ACCEPTED));
        self::assertTrue(ProfileClaimStatus::PENDING_ACCEPTANCE->permits(ProfileClaimStatus::EXPIRED));
        self::assertFalse(ProfileClaimStatus::ACCEPTED->permits(ProfileClaimStatus::REVOKED));
        self::assertTrue(PersonDuplicateCaseStatus::DISMISSED->isTerminal());
        self::assertTrue(PersonDuplicateCaseStatus::RESOLVED->isTerminal());
        self::assertTrue(PersonDuplicateCaseStatus::BLOCKED->isTerminal());
        self::assertFalse(PersonDuplicateCaseStatus::READY_FOR_REVIEW->isTerminal());
    }

    public function testAssertionAndDuplicateEnumsRemainBoundedAndNonLegal(): void
    {
        self::assertSame(
            ['ACCOUNT_CLAIMED', 'GUARDIAN_CONFIRMED', 'QMDB_RECORD_REVIEWED'],
            array_map(static fn (ProfileVerificationAssertionType $value): string => $value->value, ProfileVerificationAssertionType::cases()),
        );
        self::assertSame(['ACTIVE', 'REVOKED'], array_map(static fn (ProfileVerificationAssertionStatus $value): string => $value->value, ProfileVerificationAssertionStatus::cases()));
        self::assertSame(['GUARDIAN', 'PLATFORM_RECORD_REVIEW'], array_map(static fn (ProfileClaimAuthorizationType $value): string => $value->value, ProfileClaimAuthorizationType::cases()));
        self::assertSame(['SELF', 'GUARDIAN'], array_map(static fn (PersonDuplicateConsentAuthorityType $value): string => $value->value, PersonDuplicateConsentAuthorityType::cases()));
        self::assertSame(['PENDING', 'APPROVED', 'DECLINED'], array_map(static fn (PersonDuplicateConsentDecision $value): string => $value->value, PersonDuplicateConsentDecision::cases()));
        self::assertContains(PersonDuplicateResolutionOutcome::IDENTITY_LINK_CONFLICT, PersonDuplicateResolutionOutcome::cases());
        self::assertContains(PersonDuplicateResolutionOutcome::ORGANIZATION_AFFILIATION_CONFLICT, PersonDuplicateResolutionOutcome::cases());
        self::assertNotContains('GOVERNMENT_VERIFIED', array_map(static fn (ProfileVerificationAssertionType $value): string => $value->value, ProfileVerificationAssertionType::cases()));
    }

    public function testAdultEligibilityAndConflictPlanAreExplicitAndFailClosed(): void
    {
        $now = new DateTimeImmutable('2026-09-02T12:00:00+00:00', new DateTimeZone('UTC'));

        self::assertTrue(ProfileClaimEligibility::adult('2008-09-02', 18, $now));
        self::assertFalse(ProfileClaimEligibility::adult('2008-09-03', 18, $now));
        self::assertFalse(ProfileClaimEligibility::adult('', 18, $now));
        self::assertFalse(ProfileClaimEligibility::adult('not-a-date', 18, $now));
        self::assertFalse(ProfileClaimEligibility::adult('2000-01-01', 0, $now));
        self::assertTrue((new PersonDuplicateResolutionPlan([], 0))->isSafe());
        self::assertFalse((new PersonDuplicateResolutionPlan(['IDENTITY_LINK_CONFLICT'], 1))->isSafe());
    }

    private function configuration(string $key, int $version): IdentityResolutionConfiguration
    {
        return new IdentityResolutionConfiguration($key, $version, 128, 1_800, 10, 604_800, 50, 500, 20, 2_000, 128, 100, 900, 20, 900, 20);
    }

    /** @param array<string, string> $overrides */
    private function variables(array $overrides = []): EnvironmentVariables
    {
        return new EnvironmentVariables(array_replace([
            'APP_ENV' => 'test',
            'APP_DEBUG' => 'false',
            'APP_TIMEZONE' => 'UTC',
            'APP_PUBLIC_BASE_URL' => 'http://localhost:8080',
            'AUTH_PROFILE_CLAIM_PAIRING_HMAC_KEY' => str_repeat('p', 32),
            'AUTH_SECURITY_AUDIT_HMAC_KEY' => str_repeat('a', 32),
            'AUTH_MFA_ENCRYPTION_KEY' => str_repeat('m', 32),
        ], $overrides));
    }
}
