<?php

declare(strict_types=1);

namespace Qmdb\Tests\Tools\Ci;

use PHPUnit\Framework\TestCase;
use Qmdb\Tools\Ci\FrozenBaselineVerifier;

final class FrozenBaselineVerifierTest extends TestCase
{
    public function testRepositoryFrozenBaselineStillMatchesAllGovernedHashes(): void
    {
        $report = (new FrozenBaselineVerifier(dirname(__DIR__, 3)))->verify();
        self::assertTrue($report->passed(), implode("\n", $report->errors()));
        self::assertGreaterThanOrEqual(170, $report->checks());
    }
}
