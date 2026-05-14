<?php

declare(strict_types=1);

namespace App\Service\Fuzzy;

/**
 * Fuzzy Knapsack support with triangular fuzzy numbers (l, m, u).
 *
 * Defuzzification: centroid method  x* = (l + m + u) / 3.
 * Alpha-cut interval at level alpha: [l + alpha (m - l), u - alpha (u - m)].
 *
 * Used to derive crisp objective values before feeding the epsilon-constraint solver.
 */
final class FuzzyKnapsackService
{
    /**
     * Defuzzify a triangular number (l, m, u) by the centroid rule.
     *
     * @param array{0:float,1:float,2:float} $tfn
     */
    public function defuzzifyCentroid(array $tfn): float
    {
        [$l, $m, $u] = $tfn;
        return ($l + $m + $u) / 3.0;
    }

    /**
     * Alpha-cut middle value (e.g. alpha = 0.5 => pessimistic / optimistic hybrid).
     *
     * @param array{0:float,1:float,2:float} $tfn
     */
    public function defuzzifyAlphaCut(array $tfn, float $alpha = 0.5): float
    {
        [$l, $m, $u] = $tfn;
        $alpha = max(0.0, min(1.0, $alpha));
        $lo = $l + $alpha * ($m - $l);
        $hi = $u - $alpha * ($u - $m);
        return ($lo + $hi) / 2.0;
    }

    /**
     * Build a crisp objective matrix from a list of items having fuzzyValues: list of TFNs per objective.
     *
     * @param list<array{fuzzyValues:list<array{0:float,1:float,2:float}>}> $items
     * @param string                                                        $mode  'centroid' | 'alpha'
     *
     * @return list<list<float>>  (one row per item, one column per objective)
     */
    public function crispMatrix(array $items, string $mode = 'centroid', float $alpha = 0.5): array
    {
        $out = [];
        foreach ($items as $it) {
            $row = [];
            foreach ($it['fuzzyValues'] as $tfn) {
                $row[] = $mode === 'alpha'
                    ? $this->defuzzifyAlphaCut($tfn, $alpha)
                    : $this->defuzzifyCentroid($tfn);
            }
            $out[] = $row;
        }
        return $out;
    }

    /**
     * Simple sanity check on a TFN: l <= m <= u.
     *
     * @param array{0:float,1:float,2:float} $tfn
     */
    public function isValid(array $tfn): bool
    {
        return $tfn[0] <= $tfn[1] && $tfn[1] <= $tfn[2];
    }
}
