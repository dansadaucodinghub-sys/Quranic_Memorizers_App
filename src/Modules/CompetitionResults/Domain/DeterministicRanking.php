<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionResults\Domain;

/** @phpstan-type Candidate array{participantId:string,totalUnits:int,tieBreak:array<int,int>} */
final readonly class DeterministicRanking
{
    /**
     * @param list<Candidate> $candidates
     * @return list<array{participantId:string,totalUnits:int,rank:int}>
     */
    public function standard(array $candidates): array
    {
        return $this->rank($candidates, false);
    }

    /**
     * @param list<Candidate> $candidates
     * @return list<array{participantId:string,totalUnits:int,rank:int}>
     */
    public function dense(array $candidates): array
    {
        return $this->rank($candidates, true);
    }

    /**
     * @param list<Candidate> $candidates
     * @return list<array{participantId:string,totalUnits:int,rank:int}>
     */
    private function rank(array $candidates, bool $dense): array
    {
        usort($candidates, static function (array $left, array $right): int {
            $total = $right['totalUnits'] <=> $left['totalUnits'];
            if ($total !== 0) {
                return $total;
            }
            foreach ($left['tieBreak'] as $index => $value) {
                $comparison = ($right['tieBreak'][$index] ?? 0) <=> $value;
                if ($comparison !== 0) {
                    return $comparison;
                }
            }

            return $left['participantId'] <=> $right['participantId'];
        });

        $ranked = [];
        $rank = 0;
        $previousKey = null;
        foreach ($candidates as $position => $candidate) {
            $key = $candidate['totalUnits'] . ':' . implode(':', $candidate['tieBreak']);
            if ($key !== $previousKey) {
                $rank = $dense ? $rank + 1 : $position + 1;
                $previousKey = $key;
            }
            $ranked[] = [
                'participantId' => $candidate['participantId'],
                'totalUnits' => $candidate['totalUnits'],
                'rank' => $rank,
            ];
        }

        return $ranked;
    }
}
