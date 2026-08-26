<?php

declare(strict_types=1);

namespace Qmdb\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class SupplyChainArchitectureTest extends TestCase
{
    public function testCiActionsAreImmutableAndCredentialsAreNotPersisted(): void
    {
        foreach ($this->workflows() as $workflow) {
            preg_match_all('/(?m)^\s*uses:\s*([^\s#]+)/', $workflow, $matches);
            foreach ($matches[1] as $reference) {
                self::assertMatchesRegularExpression('/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+@[a-f0-9]{40}$/', $reference);
            }
            self::assertStringContainsString('persist-credentials: false', $workflow);
        }
    }

    public function testWorkflowsUseReadOnlyPermissionsAndNoRepositorySecrets(): void
    {
        foreach ($this->workflows() as $workflow) {
            self::assertMatchesRegularExpression('/(?m)^permissions:\R\s+contents:\s+read$/', $workflow);
            self::assertStringNotContainsString('${{ secrets.', $workflow);
            self::assertStringNotContainsString('pull_request_target', $workflow);
            self::assertStringNotContainsString('continue-on-error: true', $workflow);
        }
    }

    public function testDependencyInstallsAreLockedAndScriptsAreDisabled(): void
    {
        $workflow = implode("\n", $this->workflows());
        self::assertStringContainsString('composer install', $workflow);
        self::assertStringNotContainsString('composer update', $workflow);
        self::assertStringContainsString('npm ci --ignore-scripts', $workflow);
        self::assertStringNotContainsString('npm install ', $workflow);
    }

    public function testReleaseWorkflowOnlyUploadsAfterVerification(): void
    {
        $workflow = $this->workflows()[1];
        $verify = strpos($workflow, 'php tools/build/verify-release.php');
        $upload = strpos($workflow, 'actions/upload-artifact@');
        self::assertNotFalse($verify);
        self::assertNotFalse($upload);
        self::assertLessThan($upload, $verify);
    }

    public function testSecurityToolInvocationsUseSupportedFlagsAndBoundedGeneratedExclusions(): void
    {
        $root = dirname(__DIR__, 2);
        $workflow = $this->workflows()[0];
        $gitleaks = file_get_contents($root . '/tools/security/gitleaks.toml');
        self::assertIsString($gitleaks);

        self::assertStringContainsString('actionlint -no-color', $workflow);
        self::assertStringNotContainsString('actionlint -color=never', $workflow);
        self::assertStringContainsString(
            '^(?:vendor|node_modules|\\.phpstan\\.cache|\\.build|build|coverage)/',
            $gitleaks,
        );
        self::assertStringContainsString('^docs/data/04-entity-relationship-model\\.md$', $gitleaks);
    }

    /** @return array{string, string} */
    private function workflows(): array
    {
        $root = dirname(__DIR__, 2);
        $ci = file_get_contents($root . '/.github/workflows/ci.yml');
        $release = file_get_contents($root . '/.github/workflows/release-artifact.yml');
        self::assertIsString($ci);
        self::assertIsString($release);
        return [$ci, $release];
    }
}
