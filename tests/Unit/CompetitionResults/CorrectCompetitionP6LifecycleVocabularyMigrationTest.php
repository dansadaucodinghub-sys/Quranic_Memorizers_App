<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\CompetitionResults;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\CompetitionResults\Infrastructure\Migration\CorrectCompetitionP6LifecycleVocabularyMigration;

final class CorrectCompetitionP6LifecycleVocabularyMigrationTest extends TestCase
{
    public function testItIsForwardOnlyAndCoversEveryCorrectedLifecycleVocabulary(): void
    {
        $migration = new CorrectCompetitionP6LifecycleVocabularyMigration();
        $sql = implode("\n", array_map(static fn ($step): string => $step->sql(), $migration->up()));

        self::assertFalse($migration->reversible());
        self::assertSame([], $migration->down());
        self::assertStringContainsString("'SCORING_OPEN'", $sql);
        self::assertStringContainsString("'RESULTS_PUBLISHED'", $sql);
        self::assertStringContainsString("'ASSIGNED'", $sql);
        self::assertStringContainsString("'HEAD_JUDGE'", $sql);
        self::assertStringContainsString("'TEACHER_STUDENT'", $sql);
        self::assertStringContainsString("'VOIDED'", $sql);
    }
}
