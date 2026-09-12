<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\CompetitionResults;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\CompetitionResults\Infrastructure\Persistence\MySqlCompositeForeignKeyVerifier;

final class CompositeForeignKeyCandidateKeyVerifierTest extends TestCase
{
    public function testItAcceptsAnExactCompositeParentCandidateKey(): void
    {
        self::assertTrue(MySqlCompositeForeignKeyVerifier::hasMatchingCandidateKey([
            ['public_id'],
            ['workspace_id', 'id'],
        ], ['workspace_id', 'id']));
    }

    public function testItRejectsMissingReversedPartialAndNonUniqueRepresentations(): void
    {
        self::assertFalse(MySqlCompositeForeignKeyVerifier::hasMatchingCandidateKey([], ['workspace_id', 'id']));
        self::assertFalse(MySqlCompositeForeignKeyVerifier::hasMatchingCandidateKey([['id', 'workspace_id']], ['workspace_id', 'id']));
        self::assertFalse(MySqlCompositeForeignKeyVerifier::hasMatchingCandidateKey([['workspace_id']], ['workspace_id', 'id']));
        self::assertFalse(MySqlCompositeForeignKeyVerifier::hasMatchingCandidateKey([['workspace_id', 'rubric_id']], ['workspace_id', 'id']));
    }
}
