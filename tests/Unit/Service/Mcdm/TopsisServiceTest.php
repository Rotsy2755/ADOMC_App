<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Mcdm;

use App\Service\Mcdm\TopsisService;
use App\Service\Normalization\NormalizationService;
use PHPUnit\Framework\TestCase;

final class TopsisServiceTest extends TestCase
{
    private TopsisService $svc;
    private NormalizationService $normalizer;

    protected function setUp(): void
    {
        $this->svc = new TopsisService();
        $this->normalizer = new NormalizationService();
    }

    public function testTopsisRanksIdealSolutionFirst(): void
    {
        // Solution 1 dominates: higher f1 and f2.
        $matrix = [1 => [9.0, 9.0], 2 => [5.0, 5.0], 3 => [3.0, 3.0]];
        $normalized = $this->normalizer->vector($matrix);
        $ranking = $this->svc->compute($normalized, [0.5, 0.5]);
        $this->assertSame(1, $ranking[0]['solution_id']);
        $this->assertSame(3, $ranking[2]['solution_id']);
        // Scores should be bounded in [0,1]
        foreach ($ranking as $r) {
            $this->assertGreaterThanOrEqual(0.0, $r['score']);
            $this->assertLessThanOrEqual(1.0, $r['score']);
        }
    }

    public function testTopsisRanksAreMonotonicInScore(): void
    {
        $matrix = [
            1 => [1.0, 9.0],
            2 => [5.0, 5.0],
            3 => [9.0, 1.0],
        ];
        $normalized = $this->normalizer->vector($matrix);
        $ranking = $this->svc->compute($normalized, [0.5, 0.5]);
        for ($i = 1; $i < count($ranking); $i++) {
            $this->assertLessThanOrEqual($ranking[$i - 1]['score'], $ranking[$i]['score']);
            $this->assertSame($i + 1, $ranking[$i]['rank']);
        }
    }

    public function testOngReferenceLambdaShapeStable(): void
    {
        // Normalized matrix does not matter — we only check that TOPSIS produces
        // the same top-1 recommendation for λ₁ in [0.40, 0.65] (stability spec).
        $normalized = [
            1 => [0.46, 0.55, 0.40], // medicines-like
            2 => [0.51, 0.44, 0.45], // food-like
            3 => [0.41, 0.49, 0.35], // water-like
            4 => [0.31, 0.33, 0.50], // blankets-like
            5 => [0.36, 0.38, 0.40], // tents-like
        ];
        $top1Ids = [];
        for ($l1 = 0.40; $l1 <= 0.65 + 1e-9; $l1 += 0.05) {
            $l1 = round($l1, 2);
            $lr = (1 - $l1);
            // Split the residual between f2/f3 proportionally to ONG reference 0.30/0.20.
            $l2 = $lr * (0.30 / 0.50);
            $l3 = $lr - $l2;
            $ranking = $this->svc->compute($normalized, [$l1, $l2, $l3]);
            $top1Ids[] = $ranking[0]['solution_id'];
        }
        $this->assertCount(1, array_unique($top1Ids), 'Top-1 must remain stable for λ₁ ∈ [0.40, 0.65]');
    }

    public function testTopsisEmptyInputReturnsEmpty(): void
    {
        $this->assertSame([], $this->svc->compute([], [1.0]));
    }

    public function testGetNameIsTopsis(): void
    {
        $this->assertSame('topsis', $this->svc->getName());
    }
}
