<?php

namespace App\Service\Pareto;

use App\Entity\Problem;

/**
 * Binary NSGA-II for large instances (n > 40).
 *
 * - Random initial population with feasibility repair (drops worst-ratio items
 *   until weight ≤ W)
 * - Binary tournament selection by (rank, crowding distance)
 * - Single-point crossover, bit-flip mutation
 * - Fast non-dominated sorting + crowding distance assignment
 * - Hypervolume stagnation stopping criterion (over last STAGNATION_WINDOW generations)
 */
final class NsgaIISolver
{
    public int $populationSize = 100;
    public int $maxGenerations = 500;
    public float $crossoverRate = 0.9;
    public float $mutationRate  = 0.02;
    public int $stagnationWindow = 50;
    public float $stagnationEps  = 1e-6;

    /** @param callable|null $progressCb Called with (gen:int, generations:int) */
    public function solve(Problem $problem, ?callable $progressCb = null): array
    {
        $items = [];
        foreach ($problem->getItems() as $it) {
            $items[] = [
                'id'     => $it->getId(),
                'w'      => (float) $it->getWeight(),
                'values' => array_map('floatval', $it->getValues()),
            ];
        }
        $n = count($items);
        $W = $problem->getCapacity();
        if ($n === 0 || $W <= 0) {
            return [];
        }
        $p = count($items[0]['values']);

        // Initial population
        $pop = [];
        for ($i = 0; $i < $this->populationSize; $i++) {
            $pop[] = $this->repair($this->randomIndividual($n), $items, $W);
        }

        $hvHistory = [];
        for ($gen = 0; $gen < $this->maxGenerations; $gen++) {
            $offspring = $this->evolve($pop, $items, $W);
            $union = array_merge($pop, $offspring);
            $pop = $this->selectNextGeneration($union, $items, $this->populationSize);

            $hv = $this->hypervolumeProxy($pop, $items, $p);
            $hvHistory[] = $hv;
            if ($progressCb) {
                $progressCb($gen + 1, $this->maxGenerations);
            }

            // Stagnation check
            if (count($hvHistory) >= $this->stagnationWindow) {
                $window = array_slice($hvHistory, -$this->stagnationWindow);
                $diff = max($window) - min($window);
                if ($diff < $this->stagnationEps) {
                    break;
                }
            }
        }

        // Final Pareto extraction
        $evaluated = array_map(fn($ind) => $this->evaluate($ind, $items), $pop);
        $fronts = $this->fastNonDominatedSort($evaluated);
        $front1 = $fronts[0] ?? [];

        $results = [];
        $seen = [];
        foreach ($front1 as $idx) {
            $ind = $pop[$idx];
            $key = implode(',', $ind);
            if (isset($seen[$key])) { continue; }
            $seen[$key] = true;
            $values = $evaluated[$idx]['values'];
            $selected = [];
            $weight = 0.0;
            for ($i = 0; $i < $n; $i++) {
                if ($ind[$i]) {
                    $selected[] = $items[$i]['id'];
                    $weight += $items[$i]['w'];
                }
            }
            $results[] = ['values' => $values, 'items' => $selected, 'weight' => $weight];
        }
        return $results;
    }

    private function randomIndividual(int $n): array
    {
        $ind = [];
        for ($i = 0; $i < $n; $i++) {
            $ind[] = random_int(0, 1);
        }
        return $ind;
    }

    /** Remove worst value/weight ratio items until weight ≤ W. */
    private function repair(array $ind, array $items, float $W): array
    {
        $weight = 0.0;
        $n = count($ind);
        for ($i = 0; $i < $n; $i++) {
            if ($ind[$i]) { $weight += $items[$i]['w']; }
        }
        if ($weight <= $W) { return $ind; }

        $chosen = [];
        for ($i = 0; $i < $n; $i++) {
            if ($ind[$i]) {
                $avgVal = array_sum($items[$i]['values']) / count($items[$i]['values']);
                $ratio = $items[$i]['w'] > 0 ? $avgVal / $items[$i]['w'] : $avgVal;
                $chosen[] = ['i' => $i, 'ratio' => $ratio, 'w' => $items[$i]['w']];
            }
        }
        usort($chosen, fn($a, $b) => $a['ratio'] <=> $b['ratio']);
        foreach ($chosen as $c) {
            if ($weight <= $W) { break; }
            $ind[$c['i']] = 0;
            $weight -= $c['w'];
        }
        return $ind;
    }

    private function evaluate(array $ind, array $items): array
    {
        $p = count($items[0]['values']);
        $values = array_fill(0, $p, 0.0);
        $weight = 0.0;
        $n = count($ind);
        for ($i = 0; $i < $n; $i++) {
            if ($ind[$i]) {
                for ($k = 0; $k < $p; $k++) {
                    $values[$k] += $items[$i]['values'][$k];
                }
                $weight += $items[$i]['w'];
            }
        }
        return ['values' => $values, 'weight' => $weight];
    }

    private function evolve(array $pop, array $items, float $W): array
    {
        $offspring = [];
        $popCount = count($pop);
        for ($i = 0; $i < $popCount; $i += 2) {
            [$a, $b] = [$this->tournament($pop, $items), $this->tournament($pop, $items)];
            if (mt_rand() / mt_getrandmax() < $this->crossoverRate) {
                [$a, $b] = $this->crossover($a, $b);
            }
            $a = $this->mutate($a);
            $b = $this->mutate($b);
            $offspring[] = $this->repair($a, $items, $W);
            $offspring[] = $this->repair($b, $items, $W);
        }
        return array_slice($offspring, 0, $popCount);
    }

    private function tournament(array $pop, array $items): array
    {
        $i = array_rand($pop);
        $j = array_rand($pop);
        $ei = $this->evaluate($pop[$i], $items);
        $ej = $this->evaluate($pop[$j], $items);
        return $this->dominates($ei['values'], $ej['values']) ? $pop[$i] : $pop[$j];
    }

    private function crossover(array $a, array $b): array
    {
        $n = count($a);
        if ($n < 2) { return [$a, $b]; }
        $cp = random_int(1, $n - 1);
        $c1 = array_merge(array_slice($a, 0, $cp), array_slice($b, $cp));
        $c2 = array_merge(array_slice($b, 0, $cp), array_slice($a, $cp));
        return [$c1, $c2];
    }

    private function mutate(array $ind): array
    {
        $n = count($ind);
        for ($i = 0; $i < $n; $i++) {
            if (mt_rand() / mt_getrandmax() < $this->mutationRate) {
                $ind[$i] = 1 - $ind[$i];
            }
        }
        return $ind;
    }

    private function selectNextGeneration(array $union, array $items, int $size): array
    {
        $evaluated = array_map(fn($ind) => $this->evaluate($ind, $items), $union);
        $fronts = $this->fastNonDominatedSort($evaluated);

        $next = [];
        foreach ($fronts as $front) {
            if (count($next) + count($front) <= $size) {
                foreach ($front as $idx) { $next[] = $union[$idx]; }
            } else {
                $distances = $this->crowdingDistance($front, $evaluated);
                arsort($distances);
                foreach (array_keys($distances) as $idx) {
                    if (count($next) >= $size) { break; }
                    $next[] = $union[$idx];
                }
                break;
            }
            if (count($next) >= $size) { break; }
        }
        return array_slice($next, 0, $size);
    }

    private function fastNonDominatedSort(array $evaluated): array
    {
        $n = count($evaluated);
        $S = array_fill(0, $n, []);
        $nDom = array_fill(0, $n, 0);
        $fronts = [[]];
        for ($p = 0; $p < $n; $p++) {
            for ($q = 0; $q < $n; $q++) {
                if ($p === $q) { continue; }
                if ($this->dominates($evaluated[$p]['values'], $evaluated[$q]['values'])) {
                    $S[$p][] = $q;
                } elseif ($this->dominates($evaluated[$q]['values'], $evaluated[$p]['values'])) {
                    $nDom[$p]++;
                }
            }
            if ($nDom[$p] === 0) { $fronts[0][] = $p; }
        }
        $i = 0;
        while (!empty($fronts[$i])) {
            $next = [];
            foreach ($fronts[$i] as $p) {
                foreach ($S[$p] as $q) {
                    if (--$nDom[$q] === 0) { $next[] = $q; }
                }
            }
            $i++;
            $fronts[$i] = $next;
        }
        array_pop($fronts); // last is empty
        return $fronts;
    }

    private function crowdingDistance(array $front, array $evaluated): array
    {
        $dist = [];
        foreach ($front as $idx) { $dist[$idx] = 0.0; }
        if (count($front) < 2) {
            foreach ($front as $idx) { $dist[$idx] = INF; }
            return $dist;
        }
        $p = count($evaluated[$front[0]]['values']);
        for ($k = 0; $k < $p; $k++) {
            usort($front, fn($a, $b) => $evaluated[$a]['values'][$k] <=> $evaluated[$b]['values'][$k]);
            $dist[$front[0]] = INF;
            $dist[$front[count($front) - 1]] = INF;
            $range = $evaluated[end($front)]['values'][$k] - $evaluated[reset($front)]['values'][$k];
            if ($range <= 0) { continue; }
            for ($i = 1; $i < count($front) - 1; $i++) {
                $dist[$front[$i]] += ($evaluated[$front[$i + 1]]['values'][$k] - $evaluated[$front[$i - 1]]['values'][$k]) / $range;
            }
        }
        return $dist;
    }

    private function dominates(array $a, array $b): bool
    {
        $strict = false;
        for ($k = 0, $p = count($a); $k < $p; $k++) {
            if ($a[$k] < $b[$k] - 1e-9) { return false; }
            if ($a[$k] > $b[$k] + 1e-9) { $strict = true; }
        }
        return $strict;
    }

    /** Cheap proxy for HV: sum of objective maxima across the first front. */
    private function hypervolumeProxy(array $pop, array $items, int $p): float
    {
        $maxVals = array_fill(0, $p, 0.0);
        foreach ($pop as $ind) {
            $ev = $this->evaluate($ind, $items);
            for ($k = 0; $k < $p; $k++) {
                if ($ev['values'][$k] > $maxVals[$k]) { $maxVals[$k] = $ev['values'][$k]; }
            }
        }
        return array_sum($maxVals);
    }
}
