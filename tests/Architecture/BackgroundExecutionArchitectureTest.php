<?php

declare(strict_types=1);

namespace Qmdb\Tests\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class BackgroundExecutionArchitectureTest extends TestCase
{
    public function testBackgroundRegistriesAndProductionTaskRegistrationsAreExplicit(): void
    {
        $module = $this->read('src/Bootstrap/Module/BackgroundExecutionFoundationModule.php');
        $console = $this->read('src/Bootstrap/Module/ConsoleFoundationModule.php');

        self::assertStringContainsString('(new BackgroundJobHandlerRegistry())->build()', $module);
        self::assertStringContainsString('ScheduledTaskMap::class', $module);
        self::assertStringContainsString(
            "new ScheduledTaskId('identity.security_notifications.deliver')",
            $this->read('src/Bootstrap/Module/IdentitySecurityNotificationsModule.php'),
        );
        self::assertStringContainsString('new WorkerRunConsoleCommand', $module);
        self::assertStringContainsString('new ScheduleListConsoleCommand', $module);
        self::assertStringContainsString('new ScheduleRunConsoleCommand', $module);
        self::assertStringContainsString('foundation.background', $console);
    }

    public function testNoDiscoveryForkDaemonShellOrProviderPackageExists(): void
    {
        $background = $this->sourceUnder('src/Shared/Background');
        $composer = strtolower($this->read('composer.json'));

        self::assertDoesNotMatchRegularExpression(
            '/\b(?:glob|scandir|shell_exec|system|passthru|popen|proc_open|pcntl_fork)\s*\(/i',
            $background,
        );
        self::assertStringNotContainsString('ReflectionClass', $background);
        self::assertStringNotContainsString('daemonize', strtolower($background));
        $packages = [
            'redis', 'rabbitmq', 'kafka', 'queue', 'cron-expression',
            'symfony/console', 'reactphp', 'amphp',
        ];
        foreach ($packages as $package) {
            self::assertStringNotContainsString($package, $composer);
        }
    }

    public function testHttpCannotReachWorkerSchedulerOrSchemaMutationCommands(): void
    {
        $routes = $this->read('routes/web.php');
        $http = $this->sourceUnder('src/Shared/Http') . $this->read('src/Bootstrap/Module/HttpFoundationModule.php');

        foreach (['worker:run', 'schedule:run', 'db:migrate', 'BackgroundWorker', 'Scheduler'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $routes . $http);
        }
        self::assertStringNotContainsString(
            'foundation.background',
            $this->read('src/Bootstrap/Module/HttpFoundationModule.php'),
        );
    }

    public function testBackgroundObjectsDoNotReceiveContainerAndWorkerDoesNotHoldTransactions(): void
    {
        $background = $this->sourceUnder('src/Shared/Background');

        self::assertStringNotContainsString('Psr\\Container', $background);
        self::assertStringNotContainsString('CompiledContainer', $background);
        self::assertStringNotContainsString('ContainerInterface', $background);
        self::assertStringNotContainsString(
            'TransactionManager $',
            $this->read('src/Shared/Background/Worker/BackgroundWorker.php'),
        );
    }

    public function testNoFrontendAssetOrBusinessJobWasIntroducedByBackgroundFoundation(): void
    {
        self::assertDirectoryDoesNotExist($this->root() . '/src/Shared/Background/Http');
        self::assertDirectoryDoesNotExist($this->root() . '/src/Shared/Background/Frontend');
        self::assertDirectoryDoesNotExist($this->root() . '/src/Modules/Background');
        self::assertSame([], $this->filesUnder('src/Shared/Background', ['js', 'jsx', 'ts', 'tsx', 'vue']));
    }

    private function sourceUnder(string $relative): string
    {
        $source = '';
        foreach ($this->filesUnder($relative, ['php']) as $path) {
            $source .= $this->readAbsolute($path);
        }

        return $source;
    }

    /**
     * @param list<string> $extensions
     * @return list<string>
     */
    private function filesUnder(string $relative, array $extensions): array
    {
        $files = [];
        $directory = $this->root() . '/' . $relative;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if (
                $file instanceof SplFileInfo
                && $file->isFile()
                && in_array(strtolower($file->getExtension()), $extensions, true)
            ) {
                $files[] = str_replace('\\', '/', $file->getPathname());
            }
        }
        sort($files);

        return $files;
    }

    private function read(string $relative): string
    {
        return $this->readAbsolute($this->root() . '/' . $relative);
    }

    private function readAbsolute(string $path): string
    {
        $contents = file_get_contents($path);
        self::assertIsString($contents);

        return $contents;
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
