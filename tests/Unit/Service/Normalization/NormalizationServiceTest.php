<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Normalization;

use App\Service\Normalization\NormalizationService;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for the ONG reference case:
 * sqrt(9^2+10^2+8^2+6^2+7^2+9^2+5^2+6^2) = sqrt(472) ≈ 21.732
 * Medicines : 10/21.732 ≈ 0.4603    (item 2 on f2)
 * Radio     :  5/21.732 ≈ 0.2301    (item 7 on f1)
 */
final class NormalizationServiceTest extends TestCase
{
    private NormalizationService $svc;

    protected function setUp(): void
    {
        $this->svc = new NormalizationService();
    }

    /** ONG reference f1 column: [9, 10, 8, 6, 7, 9, 5, 6] → sqrt(472) */
    public function testOngReferenceDenominatorOnF1(): void
    {
        $matrix = [
            1 => [9.0],
            2 => [10.0],
            3 => [8.0],
            4 => [6.0],
            5 => [7.0],
            6 => [9.0],
            7 => [5.0],
            8 => [6.0],
        ];
        $out = $this->svc->vector($matrix);
        $denom = sqrt(472.0);
        $this->assertEqualsWithDelta(21.7256, $denom, 1e-3);
        $this->assertEqualsWithDelta(10.0 / $denom, $out[2][0], 1e-6);
        $this->assertEqualsWithDelta(0.4603, $out[2][0], 1e-3);
        $this->assertEqualsWithDelta(5.0 / $denom, $out[7][0], 1e-6);
        $this->assertEqualsWithDelta(0.2301, $out[7][0], 1e-3);
    }

    public function testVectorNormalizationSumsOfSquaresEqualOne(): void
    {
        $matrix = [1 => [3.0, 4.0], 2 => [4.0, 3.0]];
        $out = $this->svc->vector($matrix);
        $ss1 = $out[1][0] ** 2 + $out[2][0] ** 2;
        $ss2 = $out[1][1] ** 2 + $out[2][1] ** 2;
        $this->assertEqualsWithDelta(1.0, $ss1, 1e-9);
        $this->assertEqualsWithDelta(1.0, $ss2, 1e-9);
    }

    public function testMinMaxBounds(): void
    {
        $matrix = [1 => [2.0, 10.0], 2 => [8.0, 20.0], 3 => [5.0, 15.0]];
        $out = $this->svc->minMax($matrix);
        $this->assertEqualsWithDelta(0.0, $out[1][0], 1e-9);
        $this->assertEqualsWithDelta(1.0, $out[2][0], 1e-9);
        $this->assertEqualsWithDelta(0.5, $out[3][0], 1e-9);
        $this->assertEqualsWithDelta(0.0, $out[1][1], 1e-9);
        $this->assertEqualsWithDelta(1.0, $out[2][1], 1e-9);
    }

    public function testZScoreMeanZero(): void
    {
        $matrix = [1 => [10.0], 2 => [20.0], 3 => [30.0]];
        $out = $this->svc->zScore($matrix);
        $mean = ($out[1][0] + $out[2][0] + $out[3][0]) / 3.0;
        $this->assertEqualsWithDelta(0.0, $mean, 1e-9);
    }

    public function testSpearmanIdenticalRankingsIsOne(): void
    {
        $this->assertEqualsWithDelta(
            1.0,
            $this->svc->spearman([1 => 1, 2 => 2, 3 => 3], [1 => 1, 2 => 2, 3 => 3]),
            1e-9
        );
    }

    public function testSpearmanReversedRankingsIsMinusOne(): void
    {
        $this->assertEqualsWithDelta(
            -1.0,
            $this->svc->spearman([1 => 1, 2 => 2, 3 => 3], [1 => 3, 2 => 2, 3 => 1]),
            1e-9
        );
    }

    public function testAllReturnsAllThreeNormalizations(): void
    {
        $matrix = [1 => [1.0, 2.0], 2 => [3.0, 4.0]];
        $all = $this->svc->all($matrix);
        $this->assertArrayHasKey('vector', $all);
        $this->assertArrayHasKey('minmax', $all);
        $this->assertArrayHasKey('zscore', $all);
    }

    public function testEmptyMatrixReturnsEmpty(): void
    {
        $this->assertSame([], $this->svc->vector([]));
        $this->assertSame([], $this->svc->minMax([]));
        $this->assertSame([], $this->svc->zScore([]));
    }
}
