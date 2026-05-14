<?php

declare(strict_types=1);

namespace App\Service\Scenario;

use App\Service\Mcdm\TopsisService;
use App\Service\Normalization\NormalizationService;

/**
 * Simultaneous comparison of up to 5 weight scenarios on the same Pareto front.
 * Produces a ranking table (rows = solutions, columns = scenarios) and
 * highlights "stable top-3" solutions.
 */
final class ScenarioComparisonService
{
    public const MAX_SCENARIOS = 5;

    public function __construct(
        private readonly NormalizationService $normalization,
        private readonly TopsisService $topsis,
    ) {
    }

    /**
     * @param list<array{id:int,values:list<float>,label?:string}> $solutions
     * @param list<array{name:string,weights:list<float>,types?:list<string>}> $scenarios
     *
     * @return array{
     *   solutionIds:list<int>,
     *   scenarios:list<string>,
     *   rankings:list<list<int>>,
     *   scores:list<list<float>>,
     *   stableTop3:list<int>
     * }
     */
    public function compare(array $solutions, array $scenarios): array
    {
        if ($solutions === []) {
            return ['solutionIds' => [], 'scenarios' => [], 'rankings' => [], 'scores' => [], 'stableTop3' => []];
        }
        if (count($scenarios) > self::MAX_SCENARIOS) {
            $scenarios = array_slice($scenarios, 0, self::MAX_SCENARIOS);
        }

        $keyed = [];
        foreach ($solutions as $s) {
            $keyed[$s['id']] = $s['values'];
        }
        $ids  = array_map(static fn(array $s): int => $s['id'], $solutions);
        $norm = $this->normalization->vector($keyed);
        $idToIdx = array_flip($ids);

        $rankingsPerSol = array_fill(0, count($solutions), []);
        $scoresPerSol   = array_fill(0, count($solutions), []);
        $scenarioNames  = [];

        foreach ($scenarios as $sc) {
            $scenarioNames[] = (string)$sc['name'];
            $types = $sc['types'] ?? array_fill(0, count($sc['weights']), 'benefit');
            $ranking = $this->topsis->compute($norm, $sc['weights'], ['types' => $types]);
            foreach ($ranking as $r) {
                $sid = (int)$r['solution_id'];
                if (!isset($idToIdx[$sid])) {
                    continue;
                }
                $idx = $idToIdx[$sid];
                $rankingsPerSol[$idx][] = (int)$r['rank'];
                $scoresPerSol[$idx][]   = (float)$r['score'];
            }
        }

        // Stable top-3: solutions ranked <= 3 in every scenario.
        $stable = [];
        foreach ($rankingsPerSol as $idx => $ranks) {
            if ($ranks === []) {
                continue;
            }
            if (max($ranks) <= 3) {
                $stable[] = $ids[$idx];
            }
        }

        return [
            'solutionIds' => $ids,
            'scenarios'   => $scenarioNames,
            'rankings'    => $rankingsPerSol,
            'scores'      => $scoresPerSol,
            'stableTop3'  => $stable,
        ];
    }
}
