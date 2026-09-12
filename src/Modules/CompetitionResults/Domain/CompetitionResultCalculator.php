<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionResults\Domain;

/**
 * Deterministic, integer-only result calculation.  Persistence supplies one
 * row per locked judge score.  This boundary deliberately accepts no client
 * totals, rank values, or display labels.
 */
final readonly class CompetitionResultCalculator
{
    public function __construct(
        private PanelScoreAggregator $aggregator,
        private DeterministicRanking $ranking,
    ) {
    }

    /**
     * @param list<array{participant_public_id:string,public_label:string,total_units:int,score_sheet_checksum:string}> $lockedScores
     * @param list<array{basis:string,direction:string}> $tieBreakRules
     */
    public function calculate(string $aggregationMethod, array $lockedScores, array $tieBreakRules, bool $denseRanking = false): CalculatedCompetitionResult
    {
        if ($lockedScores === []) {
            throw new \DomainException('At least one locked score sheet is required.');
        }

        /** @var array<string,array{label:string,scores:list<int>,checksums:list<string>}> $participants */
        $participants = [];
        foreach ($lockedScores as $score) {
            $participant = $score['participant_public_id'];
            if ($participant === '' || $score['public_label'] === '' || $score['score_sheet_checksum'] === '') {
                throw new \DomainException('Locked score-sheet input is incomplete.');
            }
            $participants[$participant] ??= ['label' => $score['public_label'], 'scores' => [], 'checksums' => []];
            if ($participants[$participant]['label'] !== $score['public_label']) {
                throw new \DomainException('A participant has inconsistent public labels.');
            }
            $participants[$participant]['scores'][] = $score['total_units'];
            $participants[$participant]['checksums'][] = $score['score_sheet_checksum'];
        }
        ksort($participants, SORT_STRING);

        $inputRows = [];
        $candidates = [];
        foreach ($participants as $participantPublicId => $participant) {
            sort($participant['scores'], SORT_NUMERIC);
            sort($participant['checksums'], SORT_STRING);
            $total = $this->aggregator->aggregate($aggregationMethod, $participant['scores']);
            $tieBreak = $this->tieBreakVector($total, $participant['scores'], $tieBreakRules, $participantPublicId);
            $inputRows[] = [
                'participant_public_id' => $participantPublicId,
                'score_sheet_checksums' => $participant['checksums'],
                'scores' => $participant['scores'],
                'total_units' => $total,
                'tie_break' => $tieBreak,
            ];
            $candidates[] = $this->candidate($participantPublicId, $total, $tieBreak);
        }
        $inputChecksum = hash('sha256', self::canonicalJson(['method' => $aggregationMethod, 'participants' => $inputRows]));
        $ranked = $denseRanking ? $this->ranking->dense($candidates) : $this->ranking->standard($candidates);

        $rows = [];
        foreach ($ranked as $row) {
            $rows[] = [
                'participantPublicId' => $row['participantId'],
                'totalUnits' => $row['totalUnits'],
                'rank' => $row['rank'],
                'publicLabel' => $participants[$row['participantId']]['label'],
            ];
        }
        $resultChecksum = hash('sha256', self::canonicalJson(['input_checksum' => $inputChecksum, 'rows' => $rows]));

        return new CalculatedCompetitionResult($rows, $inputChecksum, $resultChecksum);
    }

    /**
     * @param non-empty-list<int> $scores
     * @param list<array{basis:string,direction:string}> $rules
     * @return list<int>
     */
    private function tieBreakVector(int $total, array $scores, array $rules, string $participantPublicId): array
    {
        $values = [];
        foreach ($rules as $rule) {
            $value = match ($rule['basis']) {
                'TOTAL' => $total,
                'PENALTY' => 0,
                'CRITERION' => max($scores),
                'ROSTER_SEQUENCE' => -intval(substr(hash('sha256', $participantPublicId), 0, 7), 16),
                default => throw new \DomainException('Unsupported tie-break basis.'),
            };
            $values[] = $rule['direction'] === 'ASC' ? -$value : $value;
        }

        return $values;
    }

    /**
     * @param array<int,int> $tieBreak
     * @return array{participantId:string,totalUnits:int,tieBreak:array<int,int>}
     */
    private function candidate(string $participantPublicId, int $total, array $tieBreak): array
    {
        return ['participantId' => $participantPublicId, 'totalUnits' => $total, 'tieBreak' => $tieBreak];
    }

    /** @param array<string,mixed> $payload */
    private static function canonicalJson(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }
}
