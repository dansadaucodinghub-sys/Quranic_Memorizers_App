<?php

declare(strict_types=1);

namespace Qmdb\Tests\Tools\Ci;

use PHPUnit\Framework\TestCase;
use Qmdb\Tools\Ci\WorkflowPolicyVerifier;
use Qmdb\Tools\Support\FileSystem;

final class WorkflowPolicyVerifierTest extends TestCase
{
    public function testRepositoryWorkflowsPassStaticSecurityPolicy(): void
    {
        $report = (new WorkflowPolicyVerifier(dirname(__DIR__, 3)))->verify();
        self::assertTrue($report->passed(), implode("\n", $report->errors()));
    }

    public function testUnsafeWorkflowConstructsFailClosed(): void
    {
        $root = sys_get_temp_dir() . '/qmdb-workflow-' . bin2hex(random_bytes(8));
        mkdir($root . '/.github/workflows', 0755, true);
        $unsafe = <<<'YAML'
name: Unsafe
on: pull_request_target
permissions: write-all
jobs:
  unsafe:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@main
      - run: echo "${{ github.event.pull_request.title }}"
YAML;
        file_put_contents($root . '/.github/workflows/ci.yml', $unsafe);
        file_put_contents($root . '/.github/workflows/release-artifact.yml', $unsafe);
        try {
            $report = (new WorkflowPolicyVerifier($root))->verify();
            self::assertFalse($report->passed());
            self::assertGreaterThanOrEqual(8, count($report->errors()));
        } finally {
            FileSystem::removeTree(dirname($root), $root);
        }
    }
}
