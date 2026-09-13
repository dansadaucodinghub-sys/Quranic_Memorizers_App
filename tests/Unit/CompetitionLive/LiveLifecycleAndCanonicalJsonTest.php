<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\CompetitionLive;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\CompetitionLive\Domain\LiveParticipantLifecycle;
use Qmdb\Modules\CompetitionLive\Domain\LiveSessionLifecycle;
use Qmdb\Modules\CompetitionLive\Infrastructure\Persistence\CanonicalJson;

final class LiveLifecycleAndCanonicalJsonTest extends TestCase
{
    public function testLiveSessionLifecycleRejectsTerminalAndSkippedTransitions(): void
    {
        $lifecycle = new LiveSessionLifecycle();
        $lifecycle->assertTransition('PLANNED', 'OPEN');
        $lifecycle->assertTransition('OPEN', 'RECOVERING');
        $lifecycle->assertTransition('RECOVERING', 'OPEN');

        $this->expectException(\DomainException::class);
        $lifecycle->assertTransition('CLOSED', 'OPEN');
    }

    public function testLiveParticipantLifecycleKeepsOperationalOrderClosed(): void
    {
        $lifecycle = new LiveParticipantLifecycle();
        $lifecycle->assertTransition('SCHEDULED', 'CHECKED_IN');
        $lifecycle->assertTransition('CHECKED_IN', 'CALLED');
        $lifecycle->assertTransition('CALLED', 'READY');
        $lifecycle->assertTransition('READY', 'PERFORMING');
        $lifecycle->assertTransition('PERFORMING', 'COMPLETED');

        $this->expectException(\DomainException::class);
        $lifecycle->assertTransition('COMPLETED', 'PERFORMING');
    }

    public function testCanonicalJsonIsStableAcrossInputKeyOrder(): void
    {
        $first = CanonicalJson::encode(['status' => 'OPEN', 'session' => ['round' => 'R1', 'edition' => 'e1']]);
        $second = CanonicalJson::encode(['session' => ['edition' => 'e1', 'round' => 'R1'], 'status' => 'OPEN']);

        self::assertSame($first, $second);
        self::assertSame('{"session":{"edition":"e1","round":"R1"},"status":"OPEN"}', $first);
    }

    public function testCanonicalJsonRejectsNulAndFloatingPointAuthority(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CanonicalJson::encode(['raw' => "\0"]);
    }
}
