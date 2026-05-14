<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Pareto;

use App\Service\Pareto\EpsilonConstraintSolver;
use PHPUnit\Framework\TestCase;

/**
 * ONG reference case — ε-constraint must produce at least 5 non-dominated
 * solutions for the 8-item / 30-kg / 3-objective problem.
 */
final class EpsilonConstraintSolverTest extends TestCase
{
    private EpsilonConstraintSolver $solver;

    protected function setUp(): void
    {
        $this->solver = new EpsilonConstraintSolver();
    }

    public function testOngReferenceProducesAtLeastFiveParetoSolutions(): void
    {
        $items = [
            ['id' => 1, 'w' =>  8, 'values' => [9.0, 10.0, 8.0]],
            ['id' => 2, 'w' => 12, 'values' => [10.0, 8.0, 9.0]],
            ['id' => 3, 'w' => 15, 'values' => [8.0, 9.0, 7.0]],
            ['id' => 4, 'w' =>  5, 'values' => [6.0, 6.0, 10.0]],
            ['id' => 5, 'w' => 10, 'values' => [7.0, 7.0, 8.0]],
            ['id' => 6, 'w' =>  7, 'values' => [9.0, 8.0, 9.0]],
            ['id' => 7, 'w' =>  3, 'values' => [5.0, 7.0, 6.0]],
            ['id' => 8, 'w' =>  9, 'values' => [6.0, 5.0, 7.0]],
        ];
        $front = $this->solver->solveRaw($items, 30);
        $this->assertGreaterThanOrEqual(5, count($front), 'Pareto front must contain ≥ 5 non-dominated solutions.');
        foreach ($front as $sol) {
            $this->assertLessThanOrEqual(30, $sol['weight'], 'Capacity must not be violated.');
            $this->assertCount(3, $sol['values']);
        }
    }

    public function testParetoFrontIsNonDominated(): void
    {
        $items = [
            ['id' => 1, 'w' => 2, 'values' => [3.0, 1.0]],
            ['id' => 2, 'w' => 3, 'values' => [4.0, 2.0]],
            ['id' => 3, 'w' => 4, 'values' => [2.0, 5.0]],
            ['id' => 4, 'w' => 5, 'values' => [1.0, 6.0]],
        ];
        $front = $this->solver->solveRaw($items, 10);
        // Pairwise non-domination: no solution a,b exists where a dominates b.
        foreach ($front as $a) {
            foreach ($front as $b) {
                if ($a === $b) {
                    continue;
                }
                $strict = false;
                $dominates = true;
                for ($k = 0, $p = count($a['values']); $k < $p; $k++) {
                    if ($a['values'][$k] < $b['values'][$k] - 1e-9) {
                        $dominates = false;
                        break;
                    }
                    if ($a['values'][$k] > $b['values'][$k] + 1e-9) {
                        $strict = true;
                    }
                }
                if ($dominates && $strict) {
                    $this->fail('Pareto front contains a dominated pair.');
                }
            }
        }
        $this->assertTrue(true);
    }

    public function testEmptyInputsReturnEmpty(): void
    {
        $this->assertSame([], $this->solver->solveRaw([], 10));
        $this->assertSame([], $this->solver->solveRaw([['id' => 1, 'w' => 1, 'values' => [1.0]]], 0));
    }

    public function testPerformanceOnSmallInstance(): void
    {
        $items = [];
        for ($i = 1; $i <= 10; $i++) {
            $items[] = ['id' => $i, 'w' => $i, 'values' => [(float) $i, (float) (11 - $i)]];
        }
        $start = microtime(true);
        $front = $this->solver->solveRaw($items, 20);
        $elapsed = microtime(true) - $start;
        $this->assertNotEmpty($front);
        $this->assertLessThan(2.0, $elapsed, 'Specification requires < 2s for n ≤ 30, p = 2.');
    }
}
