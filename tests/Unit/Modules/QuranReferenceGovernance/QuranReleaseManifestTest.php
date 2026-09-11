<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Modules\QuranReferenceGovernance;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\QuranReferenceGovernance\Domain\QuranReleaseManifest;

final class QuranReleaseManifestTest extends TestCase
{
    public function testCanonicalManifestSortsObjectKeysWithoutReorderingArrays(): void
    {
        $manifest = new QuranReleaseManifest();
        $first = $manifest->canonicalize(['z' => 'final', 'artifacts' => [['code' => 'B'], ['code' => 'A']], 'a' => 'first']);
        $second = $manifest->canonicalize(['a' => 'first', 'artifacts' => [['code' => 'B'], ['code' => 'A']], 'z' => 'final']);

        self::assertSame($first, $second);
        self::assertSame('{"a":"first","artifacts":[{"code":"B"},{"code":"A"}],"schema":"qmdb.quran.release-manifest.v1","z":"final"}', $first['canonical_json']);
        self::assertSame(hash('sha256', $first['canonical_json']), $first['sha256']);
    }

    public function testCanonicalManifestRejectsUnsafeOrNonDeterministicValues(): void
    {
        $manifest = new QuranReleaseManifest();

        foreach ([['value' => 1.2], ['value' => "bad\0value"], ["\xFF" => 'invalid']] as $document) {
            try {
                $manifest->canonicalize($document);
                self::fail('Invalid manifest data was accepted.');
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
