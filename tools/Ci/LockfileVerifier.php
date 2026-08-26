<?php

declare(strict_types=1);

namespace Qmdb\Tools\Ci;

use Qmdb\Tools\Support\JsonFile;
use Qmdb\Tools\Support\ProcessRunner;

final class LockfileVerifier
{
    public function __construct(
        private readonly string $root,
        private readonly ProcessRunner $runner = new ProcessRunner(),
    ) {
    }

    public function verify(): VerificationReport
    {
        $report = new VerificationReport();
        foreach (['composer.json', 'composer.lock', 'package.json', 'package-lock.json'] as $required) {
            $report->check(is_file($this->root . '/' . $required), $required . ' is required.');
        }
        if (!$report->passed()) {
            return $report;
        }
        try {
            $composerLock = JsonFile::readObject($this->root . '/composer.lock');
            $package = JsonFile::readObject($this->root . '/package.json');
            $packageLock = JsonFile::readObject($this->root . '/package-lock.json');
        } catch (\Throwable $exception) {
            $report->check(false, 'Lockfile JSON is invalid: ' . $exception->getMessage());
            return $report;
        }
        $composerValidate = $this->runner->run(['composer', 'validate', '--strict', '--no-check-publish'], $this->root);
        $report->check($composerValidate->exitCode === 0, 'Composer metadata mismatch: ' . $composerValidate->output());
        $report->check(($packageLock['lockfileVersion'] ?? 0) >= 3, 'npm lockfileVersion 3 or newer is required.');
        $lockPackages = is_array($packageLock['packages'] ?? null) ? $packageLock['packages'] : [];
        $rootPackage = $lockPackages[''] ?? null;
        $report->check(is_array($rootPackage), 'npm lockfile root package metadata is missing.');
        if (is_array($rootPackage)) {
            foreach (['dependencies', 'devDependencies'] as $section) {
                $expected = is_array($package[$section] ?? null) ? $package[$section] : [];
                $actual = is_array($rootPackage[$section] ?? null) ? $rootPackage[$section] : [];
                $report->check(
                    $expected === $actual,
                    'package-lock.json does not match package.json ' . $section . '.',
                );
            }
        }
        $this->verifyPackageSources($composerLock, $report, 'Composer');
        $this->verifyPackageSources($packageLock, $report, 'npm');
        return $report;
    }

    /** @param array<string, mixed> $data */
    private function verifyPackageSources(array $data, VerificationReport $report, string $label): void
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $report->check(
            preg_match('#(?:dist|source|resolved)"?\s*:\s*"http://#i', $json) !== 1,
            $label . ' lockfile uses insecure HTTP.',
        );
        $report->check(
            preg_match('#"(?:type|resolved)"\s*:\s*"(?:path|file:)#i', $json) !== 1,
            $label . ' lockfile uses a local path dependency.',
        );
        $report->check(
            preg_match('/"version"\s*:\s*"dev-(?:main|master)"/i', $json) !== 1,
            $label . ' lockfile uses a mutable branch.',
        );
    }
}
