<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionAppealAdjudication\Infrastructure\Persistence;

use PDO;
use PDOStatement;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;

/**
 * Read-only P7 appeal maintenance. It deliberately never decides an appeal,
 * creates a correction, or consumes a correction authorization.
 */
final readonly class CompetitionAppealMaintenanceService
{
    public function __construct(private DatabaseConnectionProvider $connections)
    {
    }

    public function process(int $limit = 100, bool $dryRun = false): int
    {
        $this->assertLimit($limit);

        return $this->reconcile($limit);
    }

    public function reconcile(int $limit = 100): int
    {
        $this->assertLimit($limit);
        $decisions = $this->statement("SELECT id,workspace_id,appeal_id,decision_type FROM competition_appeal_decisions ORDER BY id LIMIT {$limit}");
        $decisions->execute();
        $checked = 0;
        while (($row = $this->row($decisions)) !== null) {
            $decisionId = $this->integer($row, 'id');
            $workspaceId = $this->integer($row, 'workspace_id');
            $appealId = $this->integer($row, 'appeal_id');
            $decision = $this->string($row, 'decision_type');
            $this->assertIndependentReview($workspaceId, $appealId);
            $this->assertCorrectionAuthorizations($workspaceId, $decisionId, $decision);
            ++$checked;
        }

        return $checked;
    }

    private function assertIndependentReview(int $workspaceId, int $appealId): void
    {
        $statement = $this->statement("SELECT COUNT(DISTINCT assignment.reviewer_account_id) FROM competition_appeal_review_assignments assignment WHERE assignment.workspace_id=:workspace_id AND assignment.appeal_id=:appeal_id AND assignment.status='ACCEPTED' AND NOT EXISTS (SELECT 1 FROM competition_appeal_reviewer_conflicts conflict WHERE conflict.workspace_id=assignment.workspace_id AND conflict.review_assignment_id=assignment.id AND conflict.status IN ('DECLARED','RECUSED'))");
        $statement->execute([':workspace_id' => $workspaceId, ':appeal_id' => $appealId]);
        if ((int) $statement->fetchColumn() < 2) {
            throw new \DomainException('Appeal decision lacks two independent accepted reviewers.');
        }
    }

    private function assertCorrectionAuthorizations(int $workspaceId, int $decisionId, string $decision): void
    {
        $statement = $this->statement('SELECT correction_effect FROM competition_appeal_correction_authorizations WHERE workspace_id=:workspace_id AND appeal_decision_id=:decision_id ORDER BY correction_effect');
        $statement->execute([':workspace_id' => $workspaceId, ':decision_id' => $decisionId]);
        $effects = [];
        while (($effect = $statement->fetchColumn()) !== false) {
            if (!is_string($effect)) {
                throw new \UnexpectedValueException('Appeal correction authorization is invalid.');
            }
            $effects[] = $effect;
        }
        $expected = $decision === 'DISMISSED'
            ? ['NO_CHANGE']
            : ['PUBLICATION_CORRECTION_REQUIRED', 'RESULT_RECALCULATION_REQUIRED'];
        if ($effects !== $expected) {
            throw new \DomainException('Appeal correction authorization set is invalid.');
        }
    }

    private function assertLimit(int $limit): void
    {
        if ($limit < 1 || $limit > 500) {
            throw new \InvalidArgumentException('Appeal maintenance limit is invalid.');
        }
    }

    private function statement(string $sql): PDOStatement
    {
        $statement = $this->connections->connection()->prepare($sql);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Appeal maintenance statement could not be prepared.');
        }

        return $statement;
    }

    /** @return array<string,mixed>|null */
    private function row(PDOStatement $statement): ?array
    {
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        foreach ($row as $key => $_) {
            if (!is_string($key)) {
                throw new \UnexpectedValueException('Appeal maintenance row is invalid.');
            }
        }

        return $row;
    }

    /** @param array<string,mixed> $row */
    private function integer(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        if (is_int($value) || (is_string($value) && preg_match('/^(?:0|[1-9][0-9]*)$/', $value) === 1)) {
            return (int) $value;
        }
        throw new \UnexpectedValueException('Appeal maintenance integer is invalid.');
    }

    /** @param array<string,mixed> $row */
    private function string(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \UnexpectedValueException('Appeal maintenance string is invalid.');
        }

        return $value;
    }
}
