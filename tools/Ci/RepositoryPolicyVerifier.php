<?php

declare(strict_types=1);

namespace Qmdb\Tools\Ci;

use Qmdb\Tools\Policy\PathPolicy;
use Qmdb\Tools\Security\SensitiveContentScanner;
use Qmdb\Tools\Support\JsonFile;
use Qmdb\Tools\Support\ProcessRunner;

final class RepositoryPolicyVerifier
{
    public function __construct(
        private readonly string $root,
        private readonly ProcessRunner $runner = new ProcessRunner(),
    ) {
    }

    public function verify(): VerificationReport
    {
        $report = new VerificationReport();
        $composer = $this->json('composer.json', $report);
        $package = $this->json('package.json', $report);
        $tracked = $this->trackedFiles($report);

        $composerRequire = is_array($composer['require'] ?? null) ? $composer['require'] : [];
        $composerConfig = is_array($composer['config'] ?? null) ? $composer['config'] : [];

        $report->check(is_file($this->root . '/composer.lock'), 'composer.lock is required.');
        $report->check(is_file($this->root . '/package-lock.json'), 'package-lock.json is required.');
        $report->check(($composerRequire['php'] ?? null) === '^8.5', 'Composer PHP requirement must remain ^8.5.');
        $report->check(
            ($composerConfig['sort-packages'] ?? null) === true,
            'Composer package sorting must remain enabled.',
        );
        $report->check(
            isset($composerConfig['allow-plugins']) && is_array($composerConfig['allow-plugins'])
                && $composerConfig['allow-plugins'] === [],
            'Composer plugins must be explicitly denied unless approved.',
        );

        $composerNames = [];
        foreach (['require', 'require-dev'] as $section) {
            foreach (array_keys(is_array($composer[$section] ?? null) ? $composer[$section] : []) as $name) {
                $composerNames[] = strtolower((string) $name);
            }
        }
        $joinedComposer = implode("\n", $composerNames);
        $report->check(!str_contains($joinedComposer, 'pgsql'), 'PostgreSQL runtime dependencies are prohibited.');
        foreach (['laravel/framework', 'symfony/framework-bundle', 'doctrine/orm'] as $prohibited) {
            $report->check(
                !in_array($prohibited, $composerNames, true),
                'Prohibited backend framework or ORM: ' . $prohibited,
            );
        }

        $packageNames = [];
        foreach (['dependencies', 'devDependencies'] as $section) {
            foreach (array_keys(is_array($package[$section] ?? null) ? $package[$section] : []) as $name) {
                $packageNames[] = strtolower((string) $name);
            }
        }
        foreach (['jquery', 'react', 'vue', 'angular', 'alpinejs', 'htmx.org', 'next', 'nuxt'] as $prohibited) {
            $report->check(!in_array($prohibited, $packageNames, true), 'Prohibited frontend package: ' . $prohibited);
        }

        $pathPolicy = new PathPolicy();
        foreach ($tracked as $path) {
            $violation = $pathPolicy->repositoryViolation($path);
            $report->check($violation === null, $violation ?? '');
            $absolute = $this->root . '/' . $path;
            if (is_file($absolute)) {
                $report->check(
                    filesize($absolute) <= 10_000_000,
                    'Unexpected tracked file larger than 10 MB: ' . $path,
                );
            }
        }

        $javascript = $this->readTree('public/assets/js', 'js');
        foreach (['jquery', 'react', 'vue', 'angular', 'alpine', 'htmx', 'document.cookie'] as $prohibited) {
            $report->check(
                !str_contains(strtolower($javascript), strtolower($prohibited)),
                'Frontend policy violation: ' . $prohibited,
            );
        }
        $report->check(
            preg_match('/method\s*:\s*[\'\"](?:POST|PUT|PATCH|DELETE)/i', $javascript) !== 1,
            'State-changing Fetch methods are not authorized.',
        );
        $report->check(
            preg_match('/localStorage\.(?:setItem|getItem)\s*\(\s*[\'\"](?!qmdb\.theme)/i', $javascript) !== 1,
            'Only the qmdb.theme localStorage key is approved.',
        );

        $templates = $this->readTree('resources/views', 'php');
        $report->check(preg_match('/\son[a-z]+\s*=/i', $templates) !== 1, 'Inline event handlers are prohibited.');
        $externalHosts = [
            'fonts.googleapis.com', 'fonts.gstatic.com', 'cdn.jsdelivr.net', 'cdnjs.cloudflare.com', 'unpkg.com',
        ];
        foreach ($externalHosts as $host) {
            $report->check(
                !str_contains(strtolower($templates . $javascript), $host),
                'External host is prohibited: ' . $host,
            );
        }

        foreach (range(1, 10) as $batch) {
            $report->check(
                is_file(sprintf(
                    '%s/docs/implementation/reports/QMDB-P1-B%02d-implementation-report.md',
                    $this->root,
                    $batch,
                )),
                sprintf('Implementation report QMDB-P1-B%02d is missing.', $batch),
            );
        }
        $state = file_get_contents($this->root . '/docs/project/project-state.md');
        $report->check(
            is_string($state) && str_contains($state, 'Current Batch: QMDB-P1-CLOSE'),
            'Project state must identify QMDB-P1-CLOSE.',
        );
        $report->check(
            is_string($state) && str_contains($state, 'Implementation Readiness: P1_COMPLETE'),
            'Project state must retain the verified P1_COMPLETE closeout outcome.',
        );
        $report->check(
            is_string($state) && str_contains($state, 'QMDB-P0-FRZ-001'),
            'Frozen baseline marker is missing from project state.',
        );

        foreach ((new SensitiveContentScanner())->scanDirectory($this->root) as $finding) {
            $report->check(false, 'Sensitive content detected: ' . $finding);
        }
        return $report;
    }

    /** @return array<string, mixed> */
    private function json(string $relative, VerificationReport $report): array
    {
        try {
            return JsonFile::readObject($this->root . '/' . $relative);
        } catch (\Throwable $exception) {
            $report->check(false, $relative . ' is invalid: ' . $exception->getMessage());
            return [];
        }
    }

    /** @return list<string> */
    private function trackedFiles(VerificationReport $report): array
    {
        $result = $this->runner->run(['git', 'ls-files', '-z'], $this->root);
        if ($result->exitCode !== 0) {
            $report->check(false, 'Unable to enumerate Git-tracked files.');
            return [];
        }
        $files = array_values(array_filter(
            explode("\0", $result->stdout),
            static fn (string $path): bool => $path !== '',
        ));
        sort($files, SORT_STRING);
        return $files;
    }

    private function readTree(string $relative, string $extension): string
    {
        $directory = $this->root . '/' . $relative;
        if (!is_dir($directory)) {
            return '';
        }
        $contents = '';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            $directory,
            \FilesystemIterator::SKIP_DOTS,
        ));
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === $extension) {
                $value = file_get_contents($file->getPathname());
                $contents .= is_string($value) ? $value : '';
            }
        }
        return $contents;
    }
}
