<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Mcdm;

use App\Service\Mcdm\VikorService;
use App\Service\Normalization\NormalizationService;
use PHPUnit\Framework\TestCase;

final class VikorServiceTest extends TestCase
{
    private VikorService $svc;

    protected function setUp(): void
    {
        $this->svc = new VikorService();
    }

    public function testDominatedSolutionIsRankedLast(): void
    {
        $normalizer = new NormalizationService();
        $matrix = [
            1 => [9.0, 9.0],
            2 => [5.0, 5.0],
            3 => [1.0, 1.0],
        ];
        $ranking = $this->svc->compute($normalizer->vector($matrix), [0.5, 0.5]);
        $this->assertSame(1, $ranking[0]['solution_id']);
        $this->assertSame(3, $ranking[count($ranking) - 1]['solution_id']);
    }

    public function testDefaultParamsNuIsHalf(): void
    {
        $this->assertSame(['nu' => 0.5], $this->svc->getDefaultParams());
    }

    public function testEmptyMatrixReturnsEmpty(): void
    {
        $this->assertSame([], $this->svc->compute([], [1.0]));
    }

    public function testRankingIncludesSAndRIndicators(): void
    {
        $matrix = [1 => [0.5, 0.5], 2 => [0.3, 0.8]];
        $ranking = $this->svc->compute($matrix, [0.5, 0.5]);
        foreach ($ranking as $row) {
            $this->assertArrayHasKey('S', $row);
            $this->assertArrayHasKey('R', $row);
        }
    }

    public function testNuZeroMinimizesWorstCriterion(): void
    {
        $matrix = [1 => [0.9, 0.1], 2 => [0.5, 0.5]];
        // With ν=0 VIKOR reduces to minimizing R (max regret).
        $ranking = $this->svc->compute($matrix, [0.5, 0.5], ['nu' => 0.0]);
        $this->assertCount(2, $ranking);
    }
}
