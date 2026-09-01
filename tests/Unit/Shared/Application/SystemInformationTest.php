<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Application;

use PHPUnit\Framework\TestCase;
use Qmdb\Bootstrap\ApplicationMetadata;
use Qmdb\Bootstrap\RuntimeEnvironment;
use Qmdb\Bootstrap\RuntimeRequirements;
use Qmdb\Shared\Application\Query\QueryHandler;
use Qmdb\Shared\Application\System\GetSystemInformation;
use Qmdb\Shared\Application\System\GetSystemInformationHandler;
use Qmdb\Shared\Application\System\SystemInformation;
use Qmdb\Shared\Configuration\ApplicationConfigurationFactory;
use Qmdb\Shared\Configuration\ConfigurationSource;
use Qmdb\Shared\Configuration\EnvironmentVariables;
use ReflectionClass;

final class SystemInformationTest extends TestCase
{
    public function testQueryAndResultAreImmutable(): void
    {
        self::assertTrue((new ReflectionClass(GetSystemInformation::class))->isReadOnly());
        self::assertTrue((new ReflectionClass(SystemInformation::class))->isReadOnly());
    }

    public function testHandlerReturnsSafeTypedFoundationInformation(): void
    {
        $information = ($this->handler())(new GetSystemInformation());

        self::assertSame('Qur’an Memorizer DB', $information->applicationName());
        self::assertSame('QMDB', $information->applicationCode());
        self::assertSame('QMDB-P0-FRZ-001', $information->frozenBaseline());
        self::assertSame('P3', $information->currentPhase());
        self::assertSame('QMDB-P3-B03', $information->currentBatch());
        self::assertSame('test', $information->environment());
        self::assertSame('UTC', $information->timezone());
        self::assertSame('8.5.7', $information->phpVersion());
        self::assertTrue($information->runtimeRequirementsSatisfied());
    }

    public function testHandlerHasNoHttpOrContainerDependency(): void
    {
        $reflection = new ReflectionClass(GetSystemInformationHandler::class);
        self::assertTrue($reflection->implementsInterface(QueryHandler::class));

        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
            $type = (string) $parameter->getType();
            self::assertStringNotContainsString('Http', $type);
            self::assertStringNotContainsString('Container', $type);
        }
    }

    public function testResultContainsNoEnvironmentMapSecretOrFilesystemPath(): void
    {
        $information = $this->handler()(new GetSystemInformation());
        $values = [];
        foreach ((new ReflectionClass($information))->getProperties() as $property) {
            $values[] = $property->getValue($information);
        }
        $serialized = json_encode($values, JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString('SECRET', $serialized);
        self::assertStringNotContainsString(__DIR__, $serialized);
        self::assertStringNotContainsString('APP_ENV', $serialized);
    }

    private function handler(): GetSystemInformationHandler
    {
        $configuration = (new ApplicationConfigurationFactory())->create(
            new EnvironmentVariables([
                'APP_ENV' => 'test',
                'APP_DEBUG' => 'false',
                'APP_TIMEZONE' => 'UTC',
            ]),
            ConfigurationSource::PROCESS,
        );

        return new GetSystemInformationHandler(
            ApplicationMetadata::current(),
            $configuration,
            new RuntimeRequirements(),
            new RuntimeEnvironment('8.5.7', ['json', 'mbstring']),
        );
    }
}
