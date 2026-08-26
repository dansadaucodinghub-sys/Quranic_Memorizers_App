<?php

declare(strict_types=1);

namespace Qmdb\Tests\Tools\Support;

use PHPUnit\Framework\TestCase;
use Qmdb\Tools\Support\ProcessRunner;

final class ProcessRunnerTest extends TestCase
{
    public function testNpmCanBeExecutedWithoutShellInterpolation(): void
    {
        $result = (new ProcessRunner())->run(['npm', '--version'], dirname(__DIR__, 3));

        self::assertSame(0, $result->exitCode, $result->output());
        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', trim($result->stdout));
    }
}
