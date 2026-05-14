<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Sensitivity;

use App\Service\Mcdm\TopsisService;
use App\Service\Normalization\NormalizationService;
use App\Service\Sensitivity\SensitivityAnalysisService;
use PHPUnit\Framework\TestCase;

/**
 * ONG regression: the TOPSIS recommendation under λ = [0.50, 0.30, 0.20]
 * must remain stable (unchanged) for λ₁ ∈ [0.40, 0.65].
 */
final class SensitivityAnalysisServiceTest extends TestCase
{
    private SensitivityAnalysisService $svc;

    protected function setUp(): void
    {
        $this->svc = new SensitivityAnalysisService(new NormalizationService());
    }

    public function testOngRecommendationStableOnF1Interval(): void
    {
        // Objective matrix with 5 Pareto-like solutions (raw values, 3 objectives).
        $matrix = [
            1 => [20.0, 20.0, 18.0],
            2 => [19.0, 21.0, 17.0],
            3 => [22.0, 17.0, 19.0],
            4 => [15.0, 15.0, 22.0],
            5 => [18.0, 18.0, 16.0],
        ];
        $out = $this->svc->run(
            matrix: $matrix,
            baseWeights: [0.50, 0.30, 0.20],
            criterion: 0,
            min: 0.40,
            max: 0.65,
            step: 0.05,
            method: new TopsisService(),
            normalization: 'vector',
            topK: 3,
            threshold: 0.8
        );

        $this->assertNotEmpty($out['configurations']);
        $top1Ids = array_map(static fn($c) => $c['rankings'][0]['solution_id'], $out['configurations']);
        $this->assertCount(1, array_unique($top1Ids), 'Top-1 recommendation must be stable on [0.40, 0.65].');
        $this->assertNotEmpty($out['robust']);
        $this->assertIsString($out['summary']);
    }

    public function testInvalidIntervalThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->svc->run(
            matrix: [1 => [1.0]],
            baseWeights: [1.0],
            criterion: 0,
            min: 0.5,
            max: 0.1,
            step: 0.1,
            method: new TopsisService()
        );
    }

    public function testRobustnessThresholdFiltersWeakSolutions(): void
    {
        $matrix = [1 => [1.0, 0.0], 2 => [0.0, 1.0]];
        $out = $this->svc->run(
            matrix: $matrix,
            baseWeights: [0.5, 0.5],
            criterion: 0,
            min: 0.1,
            max: 0.9,
            step: 0.1,
            method: new TopsisService(),
            topK: 1,
            threshold: 0.95
        );
        // Because top-1 flips around λ=0.5, no solution can be top-1 in ≥ 95 % of configs.
        $this->assertEmpty($out['robust']);
    }
}
