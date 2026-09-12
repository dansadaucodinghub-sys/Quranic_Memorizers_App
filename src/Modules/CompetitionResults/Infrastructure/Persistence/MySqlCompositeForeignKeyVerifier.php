<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionResults\Infrastructure\Persistence;

use PDO;
use PDOStatement;
use Qmdb\Shared\Schema\State\PdoResultReader;

/**
 * Read-only Oracle MySQL verifier for every composite foreign key owned by
 * the P6 competition tables. A parent key must be unique and expose the
 * referenced columns in the exact order used by the child foreign key.
 */
final class MySqlCompositeForeignKeyVerifier
{
    /** @var list<string> */
    private const P6_TABLES = [
        'competition_rounds',
        'competition_round_participants',
        'competition_round_events',
        'competition_judges',
        'competition_judge_panels',
        'competition_judge_assignments',
        'competition_judge_assignment_events',
        'competition_judge_conflicts',
        'competition_scoring_rubrics',
        'competition_scoring_criteria',
        'competition_penalty_rules',
        'competition_tie_break_rules',
        'competition_score_sheets',
        'competition_score_entries',
        'competition_score_penalties',
        'competition_score_sheet_events',
        'competition_result_runs',
        'competition_result_rows',
        'competition_result_events',
        'competition_disqualifications',
        'competition_public_result_consents',
        'competition_appeal_windows',
        'competition_appeals',
        'competition_appeal_events',
        'competition_notification_intents',
        'competition_p6_operations',
    ];

    public function verify(PDO $pdo): void
    {
        $statement = $pdo->prepare(
            'SELECT constraint_name AS fk_name, table_name AS child_table, column_name AS child_column, '
            . 'ordinal_position AS column_position, referenced_table_name AS parent_table, '
            . 'referenced_column_name AS parent_column '
            . 'FROM information_schema.key_column_usage '
            . 'WHERE constraint_schema = DATABASE() AND referenced_table_name IS NOT NULL '
            . 'ORDER BY constraint_name ASC, ordinal_position ASC',
        );
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Composite foreign-key verification statement could not be prepared.');
        }
        $statement->execute();

        /** @var array<string,array{child_table:string,parent_table:string,child_columns:list<string>,parent_columns:list<string>}> $foreignKeys */
        $foreignKeys = [];
        foreach (PdoResultReader::rows($statement) as $row) {
            $childTable = PdoResultReader::string($row, 'child_table');
            if (!in_array($childTable, self::P6_TABLES, true)) {
                continue;
            }
            $name = PdoResultReader::string($row, 'fk_name');
            $foreignKeys[$name] ??= [
                'child_table' => $childTable,
                'parent_table' => PdoResultReader::string($row, 'parent_table'),
                'child_columns' => [],
                'parent_columns' => [],
            ];
            $foreignKeys[$name]['child_columns'][] = PdoResultReader::string($row, 'child_column');
            $foreignKeys[$name]['parent_columns'][] = PdoResultReader::string($row, 'parent_column');
        }

        $failures = [];
        foreach ($foreignKeys as $name => $foreignKey) {
            if (count($foreignKey['child_columns']) < 2) {
                continue;
            }
            if (!self::hasMatchingCandidateKey($this->uniqueIndexes($pdo, $foreignKey['parent_table']), $foreignKey['parent_columns'])) {
                $failures[] = sprintf(
                    '%s (%s -> %s) has no unique parent candidate key for (%s).',
                    $name,
                    $foreignKey['child_table'],
                    $foreignKey['parent_table'],
                    implode(', ', $foreignKey['parent_columns']),
                );
            }
        }
        if ($failures !== []) {
            throw new \RuntimeException('Composite foreign-key verification failed: ' . implode(' ', $failures));
        }
    }

    /**
     * @param list<list<string>> $uniqueIndexes
     * @param list<string> $referencedColumns
     */
    public static function hasMatchingCandidateKey(array $uniqueIndexes, array $referencedColumns): bool
    {
        foreach ($uniqueIndexes as $columns) {
            if (array_slice($columns, 0, count($referencedColumns)) === $referencedColumns) {
                return true;
            }
        }

        return false;
    }

    /** @return list<list<string>> */
    private function uniqueIndexes(PDO $pdo, string $table): array
    {
        $statement = $pdo->prepare(
            'SELECT index_name AS index_name, column_name AS column_name, seq_in_index AS column_position '
            . 'FROM information_schema.statistics '
            . 'WHERE table_schema = DATABASE() AND table_name = :table_name AND non_unique = 0 '
            . 'ORDER BY index_name ASC, seq_in_index ASC',
        );
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Composite foreign-key index verification statement could not be prepared.');
        }
        $statement->execute([':table_name' => $table]);

        /** @var array<string,list<string>> $indexes */
        $indexes = [];
        foreach (PdoResultReader::rows($statement) as $row) {
            $name = PdoResultReader::string($row, 'index_name');
            $indexes[$name] ??= [];
            $indexes[$name][] = PdoResultReader::string($row, 'column_name');
        }

        return array_values($indexes);
    }
}
