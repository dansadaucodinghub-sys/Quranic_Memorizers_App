<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\CompetitionPublication;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\CompetitionPublication\Domain\ResultPublicationLifecycle;

final class ResultPublicationLifecycleTest extends TestCase
{
    public function testPublicationLifecyclePreservesForwardOnlyHistory(): void
    {
        $lifecycle = new ResultPublicationLifecycle();
        $lifecycle->assertTransition('PREPARED', 'PROVISIONAL_PUBLISHED');
        $lifecycle->assertTransition('PROVISIONAL_PUBLISHED', 'HELD');
        $lifecycle->assertTransition('HELD', 'PROVISIONAL_PUBLISHED');
        $lifecycle->assertTransition('PROVISIONAL_PUBLISHED', 'FINALIZED');
        $lifecycle->assertTransition('FINALIZED', 'SUPERSEDED');
        $lifecycle->assertTransition('SUPERSEDED', 'ARCHIVED');

        $this->expectException(\DomainException::class);
        $lifecycle->assertTransition('ARCHIVED', 'FINALIZED');
    }

    public function testDirectFinalizationRequiresExplicitPolicy(): void
    {
        $lifecycle = new ResultPublicationLifecycle();
        $this->expectException(\DomainException::class);
        $lifecycle->assertTransition('PREPARED', 'FINALIZED');
    }
}
