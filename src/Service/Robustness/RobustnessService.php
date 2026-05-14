<?php

declare(strict_types=1);

namespace App\Service\Robustness;

use App\Service\Mcdm\TopsisService;
use App\Service\Normalization\NormalizationService;
use App\Service\Pareto\ParetoFilterService;

/**
 * Combined robustness indicator R(s) = alpha * Rp(s) + (1 - alpha) * Rm(s).
 *
 * - Rp(s): Pareto stability = proportion of neighbouring item-subset sub-problems
 *          for which s remains non-dominated inside the new Pareto front.
 * - Rm(s): MCDM ranking stability = proportion of randomly perturbed weight
 *          configurations (drawn on the simplex) for which s keeps a top-k rank
 *          according to TOPSIS.
 *
 * alpha in [0,1] drives the trade-off between the two indicators.
 */
final class RobustnessService
{
    public const DEFAULT_ALPHA = 0.5;
    public const DEFAULT_TOP_K = 3;
    public const DEFAULT_SAMPLES = 100;
    public const DEFAULT_NEIGHBOURS = 20;

    public function __construct(
        private readonly ParetoFilterService $paretoFilter,
        private readonly TopsisService $topsis,
        private readonly NormalizationService $normalization,
    ) {
    }

    /**
     * @param list<array{id:int,values:list<float>}> $solutions   Pareto solutions with objective vectors (to maximise).
     * @param list<float>                            $baseWeights base weights (sum = 1).
     * @param array                                  $options     alpha, topK, samples, neighbours, perturbation, types.
     *
     * @return list<array{id:int,Rp:float,Rm:float,R:float}>
     */
    public function compute(array $solutions, array $baseWeights, array $options = []): array
    {
        $alpha    = (float)($options['alpha']      ?? self::DEFAULT_ALPHA);
        $topK     = (int)  ($options['topK']       ?? self::DEFAULT_TOP_K);
        $samples  = (int)  ($options['samples']    ?? self::DEFAULT_SAMPLES);
        $neigh    = (int)  ($options['neighbours'] ?? self::DEFAULT_NEIGHBOURS);
        $pert     = (float)($options['perturbation'] ?? 0.15);
        $types    = $options['types'] ?? array_fill(0, count($baseWeights), 'benefit');

        $alpha = max(0.0, min(1.0, $alpha));

        $rp = $this->paretoStability($solutions, $neigh);
        $rm = $this->mcdmStability($solutions, $baseWeights, $types, $topK, $samples, $pert);

        $out = [];
        foreach ($solutions as $s) {
            $id = $s['id'];
            $rpv = $rp[$id] ?? 0.0;
            $rmv = $rm[$id] ?? 0.0;
            $r = $alpha * $rpv + (1.0 - $alpha) * $rmv;
            $out[] = ['id' => $id, 'Rp' => $rpv, 'Rm' => $rmv, 'R' => $r];
        }
        usort($out, static fn(array $a, array $b) => $b['R'] <=> $a['R']);

        return $out;
    }

    /**
     * For each solution, measure proportion of randomly perturbed neighbour fronts
     * (obtained by dropping one random objective value ±pert) in which s remains non-dominated.
     *
     * @param list<array{id:int,values:list<float>}> $solutions
     *
     * @return array<int,float>
     */
    private function paretoStability(array $solutions, int $neighbours): array
    {
        if ($solutions === []) {
            return [];
        }
        $kept = array_fill_keys(array_column($solutions, 'id'), 0);

        for ($t = 0; $t < $neighbours; ++$t) {
            $perturbed = array_map(function (array $s): array {
                $noise = array_map(
                    static fn(float $v): float => $v * (1.0 + (mt_rand(-100, 100) / 1000.0)),
                    $s['values']
                );
                return ['id' => $s['id'], 'values' => $noise];
            }, $solutions);

            $pareto = $this->paretoFilter->filter($perturbed);
            foreach ($pareto as $p) {
                if (!empty($p['is_pareto'])) {
                    $kept[$p['id']]++;
                }
            }
        }

        $out = [];
        foreach ($kept as $id => $count) {
            $out[$id] = $neighbours > 0 ? $count / $neighbours : 0.0;
        }
        return $out;
    }

    /**
     * Proportion of simplex-sampled weight vectors (Dirichlet around base) for which
     * the solution sits in the top-k ranking of TOPSIS.
     *
     * @param list<array{id:int,values:list<float>}> $solutions
     * @param list<float>                            $baseWeights
     * @param list<string>                           $types
     *
     * @return array<int,float>
     */
    private function mcdmStability(array $solutions, array $baseWeights, array $types, int $topK, int $samples, float $pert): array
    {
        if ($solutions === []) {
            return [];
        }

        // Build normalized matrix keyed by solution id so TOPSIS can return those ids.
        $keyed = [];
        foreach ($solutions as $s) {
            $keyed[$s['id']] = $s['values'];
        }
        $ids  = array_map(static fn(array $s): int => $s['id'], $solutions);
        $norm = $this->normalization->vector($keyed);

        $hit = array_fill_keys($ids, 0);

        for ($t = 0; $t < $samples; ++$t) {
            $w = $this->perturbWeights($baseWeights, $pert);
            $ranking = $this->topsis->compute($norm, $w, ['types' => $types]);
            usort($ranking, static fn(array $a, array $b) => $a['rank'] <=> $b['rank']);
            foreach (array_slice($ranking, 0, $topK) as $r) {
                $sid = (int)$r['solution_id'];
                if (isset($hit[$sid])) {
                    $hit[$sid]++;
                }
            }
        }

        $out = [];
        foreach ($hit as $id => $count) {
            $out[$id] = $samples > 0 ? $count / $samples : 0.0;
        }
        return $out;
    }

    /**
     * Gaussian-like perturbation, re-projected onto the probability simplex.
     *
     * @param list<float> $base
     *
     * @return list<float>
     */
    private function perturbWeights(array $base, float $pert): array
    {
        $w = [];
        foreach ($base as $b) {
            $noise = ((mt_rand(0, 1000) / 1000.0) - 0.5) * 2.0 * $pert;
            $w[] = max(1e-6, $b + $noise);
        }
        $sum = array_sum($w);
        return array_map(static fn(float $x): float => $x / $sum, $w);
    }
}
