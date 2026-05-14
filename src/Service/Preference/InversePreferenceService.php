<?php

namespace App\Service\Preference;

use App\Service\Mcdm\TopsisService;
use App\Service\Normalization\NormalizationService;

/**
 * Inverse preference learning.
 *
 * Given a set of chosen solutions, find weights λ ∈ Δ_p (simplex) maximising
 * the number of chosen solutions ranked #1 by TOPSIS. Resolution uses a
 * projected-gradient / Frank-Wolfe-like heuristic.
 */
final class InversePreferenceService
{
    private const MAX_ITER = 100;

    public function __construct(
        private TopsisService $topsis,
        private NormalizationService $norm,
    ) {}

    /**
     * @param array<int|string, float[]> $objectiveMatrix
     * @param list<int|string>           $chosenSolutionIds
     * @return float[]
     */
    public function suggestWeights(array $objectiveMatrix, array $chosenSolutionIds): array
    {
        if (empty($objectiveMatrix) || empty($chosenSolutionIds)) {
            return [];
        }
        $p = count(reset($objectiveMatrix));
        $lambda = array_fill(0, $p, 1.0 / $p);
        $normalized = $this->norm->vector($objectiveMatrix);
        $bestLambda = $lambda;
        $bestScore = $this->objective($normalized, $lambda, $chosenSolutionIds);

        for ($it = 0; $it < self::MAX_ITER; $it++) {
            $grad = $this->numericalGradient($normalized, $lambda, $chosenSolutionIds);

            // Frank-Wolfe: move towards the vertex of the simplex with largest gradient component
            $argmax = 0;
            for ($k = 1; $k < $p; $k++) { if ($grad[$k] > $grad[$argmax]) { $argmax = $k; } }
            $vertex = array_fill(0, $p, 0.0);
            $vertex[$argmax] = 1.0;

            $gamma = 2.0 / ($it + 2.0); // classical FW step
            $next = [];
            for ($k = 0; $k < $p; $k++) {
                $next[$k] = (1.0 - $gamma) * $lambda[$k] + $gamma * $vertex[$k];
            }
            $next = $this->simplexProject($next);
            $score = $this->objective($normalized, $next, $chosenSolutionIds);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestLambda = $next;
            }
            $lambda = $next;
        }

        return $bestLambda;
    }

    private function numericalGradient(array $normalized, array $lambda, array $chosen): array
    {
        $p = count($lambda);
        $base = $this->objective($normalized, $lambda, $chosen);
        $eps = 1e-3;
        $grad = [];
        for ($k = 0; $k < $p; $k++) {
            $plus = $lambda;
            $plus[$k] += $eps;
            $plus = $this->simplexProject($plus);
            $grad[$k] = ($this->objective($normalized, $plus, $chosen) - $base) / $eps;
        }
        return $grad;
    }

    /** How many chosen solutions are #1 under TOPSIS with these weights. */
    private function objective(array $normalized, array $lambda, array $chosen): float
    {
        $ranking = $this->topsis->compute($normalized, $lambda);
        if (empty($ranking)) { return 0.0; }
        $top = $ranking[0]['solution_id'];
        $hit = in_array($top, $chosen, true) ? 1.0 : 0.0;
        // Add small continuous component: sum of scores of chosen solutions
        $byId = [];
        foreach ($ranking as $r) { $byId[$r['solution_id']] = $r['score']; }
        $sum = 0.0;
        foreach ($chosen as $id) { $sum += (float) ($byId[$id] ?? 0.0); }
        return $hit + 0.01 * $sum;
    }

    /** Project onto the probability simplex. */
    private function simplexProject(array $v): array
    {
        $p = count($v);
        if ($p === 0) { return $v; }
        $u = $v;
        rsort($u);
        $rho = 0;
        $cum = 0.0;
        for ($i = 0; $i < $p; $i++) {
            $cum += $u[$i];
            if ($u[$i] + (1 - $cum) / ($i + 1) > 0) { $rho = $i; }
        }
        $cumR = 0.0;
        for ($i = 0; $i <= $rho; $i++) { $cumR += $u[$i]; }
        $tau = (1 - $cumR) / ($rho + 1);
        $out = [];
        foreach ($v as $x) { $out[] = max(0.0, $x + $tau); }
        // Final normalization for safety
        $s = array_sum($out);
        if ($s > 0) { foreach ($out as $i => $x) { $out[$i] = $x / $s; } }
        return $out;
    }
}
