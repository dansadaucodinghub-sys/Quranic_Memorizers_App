<?php

declare(strict_types=1);

namespace Qmdb\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class PresentationArchitectureTest extends TestCase
{
    public function testTemplatesUseTheRestrictedRenderingBoundary(): void
    {
        foreach ($this->files('resources/views', 'php') as $file) {
            $source = file_get_contents($file);
            self::assertIsString($source);
            self::assertStringContainsString('declare(strict_types=1);', $source, $file);
            self::assertStringNotContainsString('extract(', $source, $file);
            self::assertStringNotContainsString('$_GET', $source, $file);
            self::assertStringNotContainsString('$_POST', $source, $file);
            self::assertStringNotContainsString('PDO', $source, $file);
            self::assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $source, $file);
            self::assertStringNotContainsString('style="', $source, $file);
        }
    }

    public function testFrontendUsesNoFrameworkMutationOrDangerousRuntimeApi(): void
    {
        $all = '';
        foreach ($this->files('public/assets/js', 'js') as $file) {
            $source = file_get_contents($file);
            self::assertIsString($source);
            $all .= $source;
            self::assertStringNotContainsString('eval(', $source, $file);
            self::assertStringNotContainsString('new Function', $source, $file);
            self::assertStringNotContainsString('document.write', $source, $file);
            self::assertStringNotContainsString('document.cookie', $source, $file);
            if (basename($file) !== 'mutation-fetch-client.js') {
                self::assertDoesNotMatchRegularExpression(
                    '/method\s*:\s*[\'\"](?:POST|PUT|PATCH|DELETE)/i',
                    $source,
                    $file,
                );
            }
        }
        foreach (['jquery', 'react', 'vue', 'angular', 'alpine', 'htmx'] as $framework) {
            self::assertStringNotContainsString($framework, strtolower($all));
        }

        $mutationClient = (string) file_get_contents(
            dirname(__DIR__, 2) . '/public/assets/js/mutation-fetch-client.js',
        );
        foreach ([
            "target.origin !== new URL(base).origin",
            "credentials: 'same-origin'",
            "redirect: 'error'",
            "'X-QMDB-CSRF'",
            "'Idempotency-Key'",
            'retryable: false',
        ] as $requiredControl) {
            self::assertStringContainsString($requiredControl, $mutationClient);
        }
        self::assertStringNotContainsString('setTimeout(', $mutationClient);
    }

    public function testCssProvidesThemesLogicalLayoutAndAccessibilityMedia(): void
    {
        $css = '';
        foreach ($this->files('public/assets/css', 'css') as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents);
            $css .= $contents;
        }
        $requiredFeatures = [
            'high-contrast', 'emerald-gold', 'prefers-color-scheme',
            'prefers-reduced-motion', 'margin-inline', ':focus-visible',
        ];
        foreach ($requiredFeatures as $required) {
            self::assertStringContainsString($required, $css);
        }
        self::assertStringNotContainsString('fonts.googleapis.com', $css);
        self::assertStringNotContainsString('cdn.', $css);
    }

    /** @return list<string> */
    private function files(string $directory, string $extension): array
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            dirname(__DIR__, 2) . '/' . $directory,
            \FilesystemIterator::SKIP_DOTS,
        ));
        $files = [];
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === $extension) {
                $files[] = $file->getPathname();
            }
        }
        return $files;
    }
}
