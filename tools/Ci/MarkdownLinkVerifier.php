<?php

declare(strict_types=1);

namespace Qmdb\Tools\Ci;

use Qmdb\Tools\Support\FileSystem;

final class MarkdownLinkVerifier
{
    public function __construct(private readonly string $root)
    {
    }

    public function verify(): VerificationReport
    {
        $report = new VerificationReport();
        foreach (FileSystem::files($this->root) as $file) {
            $relative = FileSystem::relative($this->root, $file);
            if (!str_ends_with(strtolower($relative), '.md') || $this->ignored($relative)) {
                continue;
            }
            $contents = file_get_contents($file);
            if (!is_string($contents)) {
                $report->check(false, 'Markdown file is unreadable: ' . $relative);
                continue;
            }
            $withoutFences = preg_replace('/```[\s\S]*?```|~~~[\s\S]*?~~~/m', '', $contents) ?? $contents;
            preg_match_all('/!?\[[^\]]*\]\(([^)]+)\)/', $withoutFences, $matches, PREG_OFFSET_CAPTURE);
            foreach ($matches[1] as $capture) {
                $target = $this->target($capture[0]);
                if ($target === null) {
                    continue;
                }
                $line = substr_count(substr($withoutFences, 0, $capture[1]), "\n") + 1;
                $this->verifyTarget($file, $relative, $line, $target, $report);
            }
        }
        return $report;
    }

    private function verifyTarget(
        string $source,
        string $relative,
        int $line,
        string $target,
        VerificationReport $report,
    ): void {
        [$path, $fragment] = array_pad(explode('#', $target, 2), 2, '');
        $path = rawurldecode($path);
        $resolved = $path === '' ? $source : dirname($source) . '/' . $path;
        $real = realpath($resolved);
        $report->check($real !== false, sprintf('%s:%d broken link target: %s', $relative, $line, $target));
        if ($real === false || $fragment === '' || !is_file($real) || !str_ends_with(strtolower($real), '.md')) {
            return;
        }
        $targetContents = file_get_contents($real);
        $anchors = is_string($targetContents) ? $this->anchors($targetContents) : [];
        $requested = strtolower(rawurldecode($fragment));
        $report->check(
            in_array($requested, $anchors, true) || in_array(str_replace('-', '', $requested), array_map(
                static fn (string $anchor): string => str_replace('-', '', $anchor),
                $anchors,
            ), true),
            sprintf('%s:%d broken Markdown anchor: %s', $relative, $line, $target),
        );
    }

    /** @return list<string> */
    private function anchors(string $markdown): array
    {
        preg_match_all('/^#{1,6}\s+(.+?)\s*#*\s*$/m', $markdown, $headings);
        $seen = [];
        $anchors = [];
        foreach ($headings[1] as $heading) {
            $plain = preg_replace('/[`*_~\[\]]/', '', strtolower($heading)) ?? strtolower($heading);
            $slug = preg_replace('/[^\pL\pN _-]+/u', '', $plain) ?? $plain;
            $slug = trim(preg_replace('/\s/u', '-', $slug) ?? $slug, '-');
            $count = $seen[$slug] ?? 0;
            $seen[$slug] = $count + 1;
            $anchors[] = $count === 0 ? $slug : $slug . '-' . $count;
        }
        return $anchors;
    }

    private function target(string $raw): ?string
    {
        $raw = trim($raw);
        if (str_starts_with($raw, '<') && str_contains($raw, '>')) {
            $raw = substr($raw, 1, strpos($raw, '>') - 1);
        } elseif (preg_match('/^(\S+)(?:\s+["\'].*["\'])?$/', $raw, $match) === 1) {
            $raw = $match[1];
        }
        if ($raw === '' || preg_match('#^(?:https?://|mailto:|tel:|data:)#i', $raw) === 1) {
            return null;
        }
        return str_replace('\\', '/', $raw);
    }

    private function ignored(string $relative): bool
    {
        return preg_match('#(^|/)(\.git|vendor|node_modules|build|reports)(/|$)#', $relative) === 1;
    }
}
