<?php

declare(strict_types=1);

namespace Qmdb\Tools\Security;

use Qmdb\Tools\Support\FileSystem;

final class SensitiveContentScanner
{
    /** @var array<string, string> */
    private const PATTERNS = [
        'private key' => '/-----BEGIN (?:RSA |EC |OPENSSH |DSA )?PRIVATE KEY-----/',
        'GitHub token' => '/\bgh[pousr]_[A-Za-z0-9]{30,}\b/',
        'AWS access key' => '/\b(?:AKIA|ASIA)[A-Z0-9]{16}\b/',
        'OpenAI-style token' => '/\bsk-(?:proj-)?[A-Za-z0-9_-]{20,}\b/',
        'npm token' => '/\bnpm_[A-Za-z0-9]{30,}\b/',
        'Slack token' => '/\bxox[baprs]-[A-Za-z0-9-]{20,}\b/',
    ];

    /** @return list<string> */
    public function scanDirectory(string $root, bool $excludeDependencies = true): array
    {
        $findings = [];
        foreach (FileSystem::files($root) as $file) {
            $relative = FileSystem::relative($root, $file);
            $excluded = $excludeDependencies
                ? '#(^|/)(\.git|\.runtime|vendor|node_modules|build|reports|\.phpstan\.cache|\.phpunit\.cache)(/|$)#'
                : '#(^|/)(\.git|\.runtime|node_modules|build|reports|\.phpstan\.cache|\.phpunit\.cache)(/|$)#';
            if (preg_match($excluded, $relative) === 1) {
                continue;
            }
            if (is_link($file) || filesize($file) > 5_000_000 || $this->isBinary($file)) {
                continue;
            }
            $contents = file_get_contents($file);
            if (!is_string($contents)) {
                $findings[] = $relative . ': unreadable';
                continue;
            }
            foreach (self::PATTERNS as $name => $pattern) {
                if (preg_match($pattern, $contents) === 1) {
                    $findings[] = $relative . ': ' . $name;
                }
            }
        }
        sort($findings, SORT_STRING);
        return array_values(array_unique($findings));
    }

    private function isBinary(string $path): bool
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return true;
        }
        $sample = fread($handle, 4096);
        fclose($handle);
        return is_string($sample) && str_contains($sample, "\0");
    }
}
