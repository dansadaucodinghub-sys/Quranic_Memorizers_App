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
            '(?:^|[\\\\/])(?:\\.git|\\.runtime|vendor|node_modules|\\.phpstan\\.cache|\\.build|build|coverage)[\\\\/]',
            $gitleaks,
        );
        self::assertStringContainsString(
            '(?:^|[\\\\/])docs[\\\\/]data[\\\\/]04-entity-relationship-model\\.md$',
            $gitleaks,
        );
    }

    public function testGeneratedVulnerabilityDatabaseExclusionDoesNotHideSourceOrOtherCacheFiles(): void
    {
        $configuration = file_get_contents(dirname(__DIR__, 2) . '/tools/security/gitleaks.toml');
        self::assertIsString($configuration);
        $pattern = <<<'REGEX'
(?:^|[\\/])var[\\/]cache[\\/]trivy[\\/]db[\\/]trivy\.db$
REGEX;
        self::assertStringContainsString($pattern, $configuration);
        foreach (['var/cache/trivy/db/trivy.db', 'C:\\repo\\var\\cache\\trivy\\db\\trivy.db'] as $path) {
            self::assertSame(1, preg_match('~' . $pattern . '~', $path));
        }
        foreach (['src/trivy.db', 'tests/credentials.php', 'var/cache/trivy/db/credentials.php', 'var/cache/trivy/db/trivy.db.php', 'var/cache/other.db'] as $path) {
            self::assertSame(0, preg_match('~' . $pattern . '~', $path));
        }
    }

    public function testWindowsSecurityToolsAreChecksumPinnedAndRunEquivalentScans(): void
    {
        $root = dirname(__DIR__, 2);
        $manifest = json_decode(
            (string) file_get_contents($root . '/tools/Security/tool-versions.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($manifest);
        $windows = $manifest['windows_x86_64'] ?? null;
        self::assertIsArray($windows);
        foreach (['actionlint', 'gitleaks', 'trivy'] as $tool) {
            $definition = $windows[$tool] ?? null;
            self::assertIsArray($definition);
            $url = $definition['url'] ?? null;
            $sha256 = $definition['sha256'] ?? null;
            self::assertIsString($url);
            self::assertIsString($sha256);
            self::assertMatchesRegularExpression(
                '/\Ahttps:\/\/github\.com\/.+\/releases\/download\//',
                $url,
            );
            self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $sha256);
        }

        $installer = (string) file_get_contents($root . '/tools/windows/install-qmdb-security-tools.ps1');
        $gitleaks = (string) file_get_contents($root . '/tools/windows/run-qmdb-secret-scan.ps1');
        $trivy = (string) file_get_contents($root . '/tools/windows/run-qmdb-filesystem-scan.ps1');
        self::assertStringContainsString('Get-FileHash -Algorithm SHA256', $installer);
        self::assertStringContainsString('--redact', $gitleaks);
        self::assertStringContainsString('scan-trivy.php', $trivy);
        $scanner = (string) file_get_contents($root . '/tools/Security/TrivyFilesystemScanner.php');
        self::assertStringContainsString('--scanners', $scanner);
        self::assertStringContainsString('vuln,secret,misconfig', $scanner);
        self::assertStringContainsString('--severity', $scanner);
        self::assertStringContainsString('HIGH,CRITICAL', $scanner);
        self::assertStringContainsString('--skip-db-update', $scanner);
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
