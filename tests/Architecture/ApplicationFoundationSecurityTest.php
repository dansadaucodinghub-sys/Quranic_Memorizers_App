<?php

declare(strict_types=1);

namespace Qmdb\Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\ContainerBuilder;
use Qmdb\Shared\DependencyInjection\DependencyInjectionException;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\Module\ModuleDependencyException;
use Qmdb\Shared\Module\ModuleId;
use stdClass;

final class ApplicationFoundationSecurityTest extends TestCase
{
    public function testModuleIdentifiersRejectPathAndControlSyntax(): void
    {
        foreach (['../module', 'Foundation.Core', "foundation.\0core", 'foundation core'] as $invalid) {
            try {
                new ModuleId($invalid);
                self::fail('Invalid module identifier must fail.');
            } catch (ModuleDependencyException $exception) {
                self::assertStringNotContainsString($invalid, $exception->getMessage());
            }
        }
    }

    public function testServiceIdentifiersRejectControlCharactersWithoutLeakingValues(): void
    {
        $secret = "service.\0SECRET_VALUE";
        $builder = new ContainerBuilder();

        try {
            $builder->register(ServiceDefinition::instance($secret, 'foundation.core', new stdClass()));
            self::fail('Invalid service identifier must fail.');
        } catch (DependencyInjectionException $exception) {
            self::assertStringNotContainsString('SECRET_VALUE', $exception->getMessage());
        }
    }

    public function testFactoryCannotSelectServiceFromExternalInput(): void
    {
        $selectedByRequest = 'service.secret';
        $builder = new ContainerBuilder();
        $builder->register(ServiceDefinition::instance(
            'service.secret',
            'foundation.core',
            new stdClass(),
        ));
        $builder->register(ServiceDefinition::factory(
            'service.consumer',
            'foundation.core',
            [],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): object => $resolver->get($selectedByRequest),
            ),
        ));
        $container = $builder->build(['foundation.core' => []]);

        $this->expectException(DependencyInjectionException::class);
        $container->get('service.consumer');
    }

    public function testProductionSourcesContainNoDynamicExecution(): void
    {
        $root = dirname(__DIR__, 2) . '/src';
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $source = file_get_contents($file->getPathname());
            self::assertIsString($source);
            self::assertDoesNotMatchRegularExpression('/\b(?:eval|shell_exec)\s*\(|(?<!->)\bexec\s*\(/i', $source);
            self::assertDoesNotMatchRegularExpression('/new\s+\$[A-Za-z_]/', $source);
        }
    }
}
