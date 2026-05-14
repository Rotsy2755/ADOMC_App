<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Ahp;

use App\Service\Ahp\AhpService;
use PHPUnit\Framework\TestCase;

/**
 * ONG reference preference matrix — geometric-mean AHP,
 * CR must be < 0.10 and weights in the neighbourhood of [0.50, 0.30, 0.20].
 */
final class AhpServiceTest extends TestCase
{
    private AhpService $svc;

    protected function setUp(): void
    {
        $this->svc = new AhpService();
    }

    public function testOngReferenceMatrixIsConsistent(): void
    {
        $matrix = [
            [1.0,      2.0, 3.0],
            [1 / 2.0,  1.0, 2.0],
            [1 / 3.0,  0.5, 1.0],
        ];
        $res = $this->svc->compute($matrix);

        $this->assertTrue($res['consistent'], 'Reference AHP matrix must satisfy CR < 0.10');
        $this->assertLessThan(0.10, $res['cr']);
        $this->assertCount(3, $res['weights']);
        $this->assertEqualsWithDelta(1.0, array_sum($res['weights']), 1e-9);

        // Reference weights ≈ [0.54, 0.30, 0.16]; the dominant criterion must be 1.
        $this->assertGreaterThan($res['weights'][1], $res['weights'][0]);
        $this->assertGreaterThan($res['weights'][2], $res['weights'][1]);
    }

    public function testIdentityMatrixHasEqualWeights(): void
    {
        $n = 4;
        $I = [];
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                $I[$i][$j] = 1.0;
            }
        }
        $res = $this->svc->compute($I);
        foreach ($res['weights'] as $w) {
            $this->assertEqualsWithDelta(0.25, $w, 1e-9);
        }
        $this->assertEqualsWithDelta(0.0, $res['cr'], 1e-9);
    }

    public function testInconsistentMatrixIsFlagged(): void
    {
        // Deliberate inconsistency: a12=9, a23=9, so a13 should be ≥ 9; we set it to 1/9.
        $matrix = [
            [1.0, 9.0, 1 / 9.0],
            [1 / 9.0, 1.0, 9.0],
            [9.0, 1 / 9.0, 1.0],
        ];
        $res = $this->svc->compute($matrix);
        $this->assertFalse($res['consistent']);
        $this->assertGreaterThan(0.10, $res['cr']);
    }

    public function testWorstPairsAreReportedForInconsistency(): void
    {
        $matrix = [
            [1.0, 5.0, 1 / 7.0],
            [1 / 5.0, 1.0, 3.0],
            [7.0, 1 / 3.0, 1.0],
        ];
        $res = $this->svc->compute($matrix);
        $this->assertNotEmpty($res['worstPairs']);
        foreach ($res['worstPairs'] as $pair) {
            $this->assertArrayHasKey('i', $pair);
            $this->assertArrayHasKey('j', $pair);
            $this->assertArrayHasKey('value', $pair);
        }
    }

    public function testAggregateTwoMatrices(): void
    {
        $m1 = [[1.0, 2.0], [0.5, 1.0]];
        $m2 = [[1.0, 8.0], [1 / 8.0, 1.0]];
        $agg = $this->svc->aggregate([$m1, $m2]);
        $this->assertEqualsWithDelta(sqrt(16.0), $agg[0][1], 1e-9);
    }

    public function testEmptyMatrixReturnsEmpty(): void
    {
        $res = $this->svc->compute([]);
        $this->assertSame([], $res['weights']);
        $this->assertTrue($res['consistent']);
    }
}
