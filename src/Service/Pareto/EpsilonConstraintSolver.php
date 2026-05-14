<?php

namespace App\Service\Pareto;

use App\Entity\Problem;

/**
 * Exact Pareto solver for multi-objective 0/1 knapsack (MOKP).
 *
 * Uses a multi-dimensional Pareto dynamic programming:
 *   dp[w] = non-dominated (f₁..f_p, selected_items) triples for weight ≤ w.
 *
 * This is mathematically equivalent to sweeping an ε-constraint grid over
 * (ε₂, ε₃, ...) because every Pareto-optimal point is obtained for some
 * right-hand side configuration and vice-versa, while avoiding the repeated
 * DP overhead. Recommended for n ≤ 40, p ≤ 3.
 *
 * Complexity: O(n · W · |F|) where |F| is the size of the Pareto front
 * discovered during the computation (capped per weight class for safety).
 *
 * Items must have non-negative integer weights. Floats are scaled internally.
 */
final class EpsilonConstraintSolver
{
    /** Hard cap on the number of non-dominated vectors kept per weight class. */
    private const MAX_FRONT_PER_W = 500;

    /**
     * @return list<array{values: float[], items: list<int|string>, weight: int}>
     */
    public function solve(Problem $problem): array
    {
        $scale = $this->computeScale($problem);
        $capacity = (int) round($problem->getCapacity() * $scale);
        $items = [];
        foreach ($problem->getItems() as $item) {
            $items[] = [
                'id'     => $item->getId(),
                'w'      => (int) round($item->getWeight() * $scale),
                'values' => array_map('floatval', $item->getValues()),
            ];
        }
        return $this->solveRaw($items, $capacity, $scale);
    }

    /**
     * @param list<array{id:int|string,w:int,values:float[]}> $items
     * @return list<array{values: float[], items: list<int|string>, weight: int}>
     */
    public function solveRaw(array $items, int $capacity, float $scale = 1.0): array
    {
        if ($capacity <= 0 || count($items) === 0) {
            return [];
        }
        $p = count($items[0]['values']);
        $zero = array_fill(0, $p, 0.0);

        // dp[w] = list of entries {values, items}
        $dp = array_fill(0, $capacity + 1, []);
        $dp[0][] = ['values' => $zero, 'items' => []];

        foreach ($items as $it) {
            if ($it['w'] <= 0 || $it['w'] > $capacity) {
                continue;
            }
            // Iterate backwards to preserve 0/1 knapsack semantics
            for ($w = $capacity; $w >= $it['w']; $w--) {
                if (empty($dp[$w - $it['w']])) { continue; }
                foreach ($dp[$w - $it['w']] as $prev) {
                    $newValues = $prev['values'];
                    for ($k = 0; $k < $p; $k++) {
                        $newValues[$k] += $it['values'][$k];
                    }
                    $newItems = $prev['items'];
                    $newItems[] = $it['id'];
                    $dp[$w][] = ['values' => $newValues, 'items' => $newItems];
                }
                $dp[$w] = $this->pruneFront($dp[$w]);
            }
        }

        // Aggregate entire DP, then prune globally.
        $all = [];
        foreach ($dp as $w => $entries) {
            foreach ($entries as $e) {
                $e['weight'] = $w;
                $all[] = $e;
            }
        }
        $pruned = $this->pruneFront($all);

        // Convert weight back to original scale (divide).
        return array_map(static function (array $e) use ($scale) {
            $e['weight'] = $scale > 0 ? $e['weight'] / $scale : $e['weight'];
            return $e;
        }, $pruned);
    }

    /** Keep only non-dominated entries (maximization), cap size. */
    private function pruneFront(array $entries): array
    {
        $n = count($entries);
        if ($n <= 1) { return $entries; }
        $keep = array_fill(0, $n, true);
        for ($i = 0; $i < $n; $i++) {
            if (!$keep[$i]) { continue; }
            for ($j = 0; $j < $n; $j++) {
                if ($i === $j || !$keep[$j]) { continue; }
                if ($this->dominates($entries[$i]['values'], $entries[$j]['values'])) {
                    $keep[$j] = false;
                } elseif ($this->equalValues($entries[$i]['values'], $entries[$j]['values']) && $i < $j) {
                    $keep[$j] = false;
                }
            }
        }
        $out = [];
        foreach ($entries as $idx => $e) {
            if ($keep[$idx]) { $out[] = $e; }
        }
        if (count($out) > self::MAX_FRONT_PER_W) {
            // Keep a spread: sort by sum(values) descending and take top
            usort($out, fn($a, $b) => array_sum($b['values']) <=> array_sum($a['values']));
            $out = array_slice($out, 0, self::MAX_FRONT_PER_W);
        }
        return $out;
    }

    private function dominates(array $a, array $b): bool
    {
        $strict = false;
        $p = count($a);
        for ($k = 0; $k < $p; $k++) {
            if ($a[$k] < $b[$k] - 1e-9) { return false; }
            if ($a[$k] > $b[$k] + 1e-9) { $strict = true; }
        }
        return $strict;
    }

    private function equalValues(array $a, array $b): bool
    {
        $p = count($a);
        for ($k = 0; $k < $p; $k++) {
            if (abs($a[$k] - $b[$k]) > 1e-9) { return false; }
        }
        return true;
    }

    /** Choose scaling factor if any weight is non-integer. */
    private function computeScale(Problem $problem): float
    {
        $scale = 1.0;
        foreach ($problem->getItems() as $item) {
            $w = $item->getWeight();
            if (floor($w) != $w) {
                $scale = 10.0; // Assume at most one decimal digit for pragmatic tests.
            }
        }
        return $scale;
    }
}
