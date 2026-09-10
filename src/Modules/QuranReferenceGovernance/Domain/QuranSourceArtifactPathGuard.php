<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Domain;

use InvalidArgumentException;

/** Allows trusted operator artifacts only beneath one approved repository directory. */
final readonly class QuranSourceArtifactPathGuard
{
    public function resolve(string $approvedDirectory, string $repositoryRelativePath): string
    {
        if ($repositoryRelativePath === '' || str_contains($repositoryRelativePath, "\0") || str_starts_with($repositoryRelativePath, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $repositoryRelativePath) === 1) {
            throw new InvalidArgumentException('Source artifact path must be repository-relative.');
        }
        $base = realpath($approvedDirectory);
        $candidate = realpath($approvedDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $repositoryRelativePath));
        if ($base === false || $candidate === false || !is_file($candidate) || !str_starts_with($candidate, $base . DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException('Source artifact path is outside the approved directory.');
        }
        return $candidate;
    }
}
