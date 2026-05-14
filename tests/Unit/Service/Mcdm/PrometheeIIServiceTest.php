<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Mcdm;

use App\Service\Mcdm\PrometheeIIService;
use PHPUnit\Framework\TestCase;

final class PrometheeIIServiceTest extends TestCase
{
    private PrometheeIIService $svc;

    protected function setUp(): void
    {
        $this->svc = new PrometheeIIService();
    }

    public function testNetFlowsSumToZero(): void
    {
        $matrix = [
            1 => [0.9, 0.1],
            2 => [0.5, 0.5],
            3 => [0.1, 0.9],
        ];
        $ranking = $this->svc->compute($matrix, [0.5, 0.5]);
        $sum = 0.0;
        foreach ($ranking as $r) {
            $sum += $r['score'];
        }
        $this->assertEqualsWithDelta(0.0, $sum, 1e-9, 'Σ Φ = 0 by construction of PROMETHEE II.');
    }

    public function testDominatedAlternativeHasLowestFlow(): void
    {
        $matrix = [
            1 => [0.9, 0.9],
            2 => [0.5, 0.5],
            3 => [0.1, 0.1],
        ];
        $ranking = $this->svc->compute($matrix, [0.5, 0.5]);
        $this->assertSame(1, $ranking[0]['solution_id']);
        $this->assertSame(3, $ranking[2]['solution_id']);
        $this->assertGreaterThan($ranking[1]['score'], $ranking[0]['score']);
    }

    public function testLinearPreferenceFunction(): void
    {
        $matrix = [1 => [1.0, 0.0], 2 => [0.0, 1.0]];
        $ranking = $this->svc->compute(
            $matrix,
            [0.5, 0.5],
            ['types' => ['linear', 'linear'], 'thresholds' => [['q' => 0.0, 'p' => 1.0], ['q' => 0.0, 'p' => 1.0]]]
        );
        $this->assertCount(2, $ranking);
    }

    public function testEmptyMatrixReturnsEmpty(): void
    {
        $this->assertSame([], $this->svc->compute([], [1.0]));
    }
}
