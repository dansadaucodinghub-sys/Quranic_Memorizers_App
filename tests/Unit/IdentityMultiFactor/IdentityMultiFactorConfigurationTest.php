<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\IdentityMultiFactor;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Modules\IdentityMultiFactor\Configuration\IdentityMultiFactorConfigurationFactory;
use Qmdb\Shared\Configuration\ApplicationConfigurationFactory;
use Qmdb\Shared\Configuration\ConfigurationSource;
use Qmdb\Shared\Configuration\EnvironmentVariables;

final class IdentityMultiFactorConfigurationTest extends TestCase
{
    public function testSecureDefaultsAreAppliedForTestEnvironment(): void
    {
        $variables = $this->variables();
        $application = (new ApplicationConfigurationFactory())->create($variables, ConfigurationSource::PROCESS);
        $configuration = (new IdentityMultiFactorConfigurationFactory())->create($variables, $application);

        self::assertSame('required', $configuration->userVerification);
        self::assertSame('none', $configuration->attestation);
        self::assertSame('localhost', $configuration->relyingPartyId);
        self::assertSame(['http://localhost:8080'], $configuration->allowedOrigins);
        self::assertSame(30, $configuration->totpPeriodSeconds);
        self::assertSame(6, $configuration->totpDigits);
        self::assertTrue($configuration->passwordlessEnabled);
    }

    public function testWildcardOriginIsRejected(): void
    {
        $variables = $this->variables(['AUTH_WEBAUTHN_ALLOWED_ORIGINS' => 'https://*.example.test']);
        $application = (new ApplicationConfigurationFactory())->create($variables, ConfigurationSource::PROCESS);

        $this->expectException(\InvalidArgumentException::class);
        (new IdentityMultiFactorConfigurationFactory())->create($variables, $application);
    }

    public function testProductionRequiresHttpsAndNonLocalhostRpId(): void
    {
        $variables = $this->variables([
            'APP_ENV' => 'production',
            'APP_PUBLIC_BASE_URL' => 'https://qmdb.example.test',
            'AUTH_WEBAUTHN_RP_ID' => 'localhost',
            'AUTH_WEBAUTHN_ALLOWED_ORIGINS' => 'http://localhost:8080',
        ]);
        $application = (new ApplicationConfigurationFactory())->create($variables, ConfigurationSource::PROCESS);

        $this->expectException(\InvalidArgumentException::class);
        (new IdentityMultiFactorConfigurationFactory())->create($variables, $application);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidRelyingPartyIds(): iterable
    {
        yield 'scheme' => ['https://qmdb.example.test'];
        yield 'port' => ['qmdb.example.test:443'];
        yield 'path' => ['qmdb.example.test/login'];
        yield 'wildcard' => ['*.example.test'];
        yield 'uppercase' => ['QMDB.example.test'];
    }

    #[DataProvider('invalidRelyingPartyIds')]
    public function testRelyingPartyIdRejectsNonHostnameValues(string $relyingPartyId): void
    {
        $variables = $this->variables(['AUTH_WEBAUTHN_RP_ID' => $relyingPartyId]);
        $application = (new ApplicationConfigurationFactory())->create($variables, ConfigurationSource::PROCESS);

        $this->expectException(\InvalidArgumentException::class);
        (new IdentityMultiFactorConfigurationFactory())->create($variables, $application);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidOrigins(): iterable
    {
        yield 'path' => ['https://qmdb.example.test/login'];
        yield 'query' => ['https://qmdb.example.test?source=test'];
        yield 'fragment' => ['https://qmdb.example.test#login'];
        yield 'credentials' => ['https://user@qmdb.example.test'];
        yield 'ftp' => ['ftp://qmdb.example.test'];
        yield 'remote HTTP' => ['http://qmdb.example.test'];
    }

    #[DataProvider('invalidOrigins')]
    public function testAllowedOriginsRejectNonOriginAndInsecureRemoteValues(string $origin): void
    {
        $variables = $this->variables(['AUTH_WEBAUTHN_ALLOWED_ORIGINS' => $origin]);
        $application = (new ApplicationConfigurationFactory())->create($variables, ConfigurationSource::PROCESS);

        $this->expectException(\InvalidArgumentException::class);
        (new IdentityMultiFactorConfigurationFactory())->create($variables, $application);
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidWebAuthnPolicies(): iterable
    {
        yield 'preferred verification' => ['AUTH_WEBAUTHN_USER_VERIFICATION', 'preferred'];
        yield 'discouraged verification' => ['AUTH_WEBAUTHN_USER_VERIFICATION', 'discouraged'];
        yield 'direct attestation' => ['AUTH_WEBAUTHN_ATTESTATION', 'direct'];
    }

    #[DataProvider('invalidWebAuthnPolicies')]
    public function testWebAuthnPolicyCannotWeakenVerificationOrAttestation(string $name, string $value): void
    {
        $variables = $this->variables([$name => $value]);
        $application = (new ApplicationConfigurationFactory())->create($variables, ConfigurationSource::PROCESS);

        $this->expectException(\InvalidArgumentException::class);
        (new IdentityMultiFactorConfigurationFactory())->create($variables, $application);
    }

    public function testProductionAcceptsExactHttpsOriginAndExplicitRpId(): void
    {
        $variables = $this->variables([
            'APP_ENV' => 'production',
            'APP_PUBLIC_BASE_URL' => 'https://qmdb.example.test',
            'AUTH_WEBAUTHN_RP_ID' => 'qmdb.example.test',
            'AUTH_WEBAUTHN_ALLOWED_ORIGINS' => 'https://qmdb.example.test',
        ]);
        $application = (new ApplicationConfigurationFactory())->create($variables, ConfigurationSource::PROCESS);
        $configuration = (new IdentityMultiFactorConfigurationFactory())->create($variables, $application);

        self::assertSame('qmdb.example.test', $configuration->relyingPartyId);
        self::assertSame(['https://qmdb.example.test'], $configuration->allowedOrigins);
        self::assertTrue($configuration->productionLike);
    }

    /** @param array<string, string> $overrides */
    private function variables(array $overrides = []): EnvironmentVariables
    {
        return new EnvironmentVariables(array_replace([
            'APP_ENV' => 'test',
            'APP_DEBUG' => 'false',
            'APP_TIMEZONE' => 'UTC',
            'APP_PUBLIC_BASE_URL' => 'http://localhost:8080',
        ], $overrides));
    }
}
