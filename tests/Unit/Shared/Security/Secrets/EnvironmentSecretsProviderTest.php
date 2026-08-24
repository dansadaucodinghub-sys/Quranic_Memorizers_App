<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Security\Secrets;

use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Configuration\EnvironmentVariables;
use Qmdb\Shared\Security\Secrets\EnvironmentSecretsProvider;
use Qmdb\Shared\Security\Secrets\SecretName;
use Qmdb\Shared\Security\Secrets\SecretUnavailableException;
use Qmdb\Shared\Security\Secrets\SecretValue;
use ReflectionClass;

final class EnvironmentSecretsProviderTest extends TestCase
{
    private const TEST_SECRET = 'QMDB_PROVIDER_SECRET_d390a9';

    public function testExistingSecretIsReturnedAsProtectedValue(): void
    {
        $provider = $this->provider(['APP_PRIVATE_SECRET' => self::TEST_SECRET]);
        $value = $provider->get(SecretName::fromString('APP_PRIVATE_SECRET'));

        self::assertInstanceOf(SecretValue::class, $value);
        self::assertSame(self::TEST_SECRET, $value->reveal());
        self::assertTrue($provider->has(SecretName::fromString('APP_PRIVATE_SECRET')));
    }

    public function testMissingSecretFailsWithOnlyTheSafeName(): void
    {
        try {
            $this->provider([])->get(SecretName::fromString('MISSING_SECRET'));
            self::fail('Missing secret must fail.');
        } catch (SecretUnavailableException $exception) {
            self::assertSame('SECRET_MISSING', $exception->reasonCode());
            self::assertStringContainsString('MISSING_SECRET', $exception->getMessage());
            self::assertStringNotContainsString(self::TEST_SECRET, $exception->getMessage());
        }
    }

    public function testEmptySecretFailsSafely(): void
    {
        try {
            $this->provider(['EMPTY_SECRET' => '  '])->get(SecretName::fromString('EMPTY_SECRET'));
            self::fail('Empty secret must fail.');
        } catch (SecretUnavailableException $exception) {
            self::assertSame('SECRET_EMPTY', $exception->reasonCode());
            self::assertFalse($this->provider(['EMPTY_SECRET' => ''])->has(SecretName::fromString('EMPTY_SECRET')));
        }
    }

    public function testProviderHasNoRawEnvironmentDump(): void
    {
        $reflection = new ReflectionClass(EnvironmentSecretsProvider::class);

        foreach (['all', 'toArray', 'raw', 'values'] as $method) {
            self::assertFalse($reflection->hasMethod($method));
        }
    }

    public function testProviderDebugOutputIsRedacted(): void
    {
        ob_start();
        var_dump($this->provider(['APP_PRIVATE_SECRET' => self::TEST_SECRET]));
        $output = (string) ob_get_clean();

        self::assertStringContainsString('[REDACTED]', $output);
        self::assertStringNotContainsString(self::TEST_SECRET, $output);
    }

    /** @param array<string, string> $values */
    private function provider(array $values): EnvironmentSecretsProvider
    {
        return new EnvironmentSecretsProvider(new EnvironmentVariables($values));
    }
}
