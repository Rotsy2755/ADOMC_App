<?php

namespace App\Service\Pareto;

/**
 * Non-dominated sorting filter. Complexity O(p·n²).
 *
 * A solution a dominates b iff a_k ≥ b_k for all k AND a_k > b_k for at least one k
 * (objectives are to be maximized).
 */
final class ParetoFilterService
{
    /**
     * @param list<array{id:int|string, values: float[]}> $solutions
     * @return list<array{id:int|string, values: float[], is_pareto:bool, dominated_by:int|string|null}>
     */
    public function filter(array $solutions): array
    {
        $n = count($solutions);
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $out[$i] = [
                'id' => $solutions[$i]['id'],
                'values' => $solutions[$i]['values'],
                'is_pareto' => true,
                'dominated_by' => null,
            ];
        }
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                if ($i === $j) { continue; }
                if ($this->dominates($solutions[$j]['values'], $solutions[$i]['values'])) {
                    $out[$i]['is_pareto'] = false;
                    $out[$i]['dominated_by'] = $solutions[$j]['id'];
                    break;
                }
            }
        }
        return $out;
    }

    /** True if a dominates b (maximization). */
    public function dominates(array $a, array $b): bool
    {
        $strict = false;
        $p = count($a);
        for ($k = 0; $k < $p; $k++) {
            if ($a[$k] < $b[$k]) { return false; }
            if ($a[$k] > $b[$k]) { $strict = true; }
        }
        return $strict;
    }
}
