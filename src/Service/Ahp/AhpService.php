<?php

namespace App\Service\Ahp;

/**
 * AHP service — geometric-mean method.
 *
 * - Pairwise comparison matrix A (n×n, values in {1/9..1..9})
 * - Priority vector λ = normalized row geometric means
 * - λ_max, CI = (λ_max-n)/(n-1), CR = CI/RI
 */
final class AhpService
{
    /** Saaty Random Index values. */
    private const RI = [0, 0, 0, 0.58, 0.90, 1.12, 1.24, 1.32, 1.41, 1.45, 1.49];
    public const CR_THRESHOLD = 0.10;

    /**
     * Compute AHP priorities for a square pairwise matrix.
     *
     * @return array{
     *   weights: float[], lambdaMax: float, ci: float, cr: float, consistent: bool,
     *   pairwiseInconsistency: array<int, array<int, float>>,
     *   worstPairs: array<int, array{i:int,j:int,value:float}>
     * }
     */
    public function compute(array $matrix): array
    {
        $n = count($matrix);
        if ($n === 0) {
            return ['weights' => [], 'lambdaMax' => 0, 'ci' => 0, 'cr' => 0, 'consistent' => true, 'pairwiseInconsistency' => [], 'worstPairs' => []];
        }

        // Geometric-mean row weights.
        $rowGeo = array_fill(0, $n, 1.0);
        for ($i = 0; $i < $n; $i++) {
            $prod = 1.0;
            for ($j = 0; $j < $n; $j++) {
                $prod *= max((float) $matrix[$i][$j], 1e-12);
            }
            $rowGeo[$i] = $prod ** (1.0 / $n);
        }
        $sum = array_sum($rowGeo);
        $weights = array_map(fn($g) => $sum > 0 ? $g / $sum : 1.0 / $n, $rowGeo);

        // λ_max = Σ_j (Σ_i a_ij · λ_i) / n  ≡  mean of (A·w)_i / w_i
        $lambdaMax = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $sumRow = 0.0;
            for ($j = 0; $j < $n; $j++) {
                $sumRow += ((float) $matrix[$i][$j]) * $weights[$j];
            }
            $lambdaMax += $weights[$i] > 0 ? $sumRow / $weights[$i] : 0.0;
        }
        $lambdaMax /= $n;

        $ci = $n > 1 ? ($lambdaMax - $n) / ($n - 1) : 0.0;
        $ri = self::RI[$n] ?? 1.49;
        $cr = $ri > 0 ? $ci / $ri : 0.0;
        $consistent = $cr < self::CR_THRESHOLD;

        // Pairwise inconsistency index (a_ij · w_j / w_i - 1).
        $inc = [];
        $worst = [];
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                if ($i === $j || $weights[$i] <= 0) {
                    $inc[$i][$j] = 0.0;
                    continue;
                }
                $value = abs(((float) $matrix[$i][$j]) * $weights[$j] / $weights[$i] - 1.0);
                $inc[$i][$j] = $value;
                if ($i < $j) {
                    $worst[] = ['i' => $i, 'j' => $j, 'value' => $value];
                }
            }
        }
        usort($worst, static fn($a, $b) => $b['value'] <=> $a['value']);
        $worst = array_slice($worst, 0, 2);

        return [
            'weights' => array_values($weights),
            'lambdaMax' => $lambdaMax,
            'ci' => $ci,
            'cr' => $cr,
            'consistent' => $consistent,
            'pairwiseInconsistency' => $inc,
            'worstPairs' => $worst,
        ];
    }

    /**
     * Aggregate multiple decider matrices by geometric median (Aczél-Alsina).
     * Simple approach: element-wise geometric mean (robust multi-decider aggregation).
     */
    public function aggregate(array $matrices): array
    {
        if (count($matrices) === 0) {
            return [];
        }
        $n = count($matrices[0]);
        $agg = [];
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                $prod = 1.0;
                foreach ($matrices as $m) {
                    $prod *= max((float) $m[$i][$j], 1e-12);
                }
                $agg[$i][$j] = $prod ** (1.0 / count($matrices));
            }
        }
        return $agg;
    }
}
