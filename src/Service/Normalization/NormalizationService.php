<?php

namespace App\Service\Normalization;

/**
 * Normalization service — three methods computed simultaneously.
 *
 * Matrix layout: $matrix[$solutionId] = [f1, f2, ..., fp]
 */
final class NormalizationService
{
    /** Vector normalization: r_ij = f_ij / sqrt(sum_i f_ij^2). */
    public function vector(array $matrix): array
    {
        if (empty($matrix)) {
            return [];
        }
        $p = count(reset($matrix));
        $denom = array_fill(0, $p, 0.0);
        foreach ($matrix as $row) {
            for ($j = 0; $j < $p; $j++) {
                $denom[$j] += ((float) ($row[$j] ?? 0)) ** 2;
            }
        }
        for ($j = 0; $j < $p; $j++) {
            $denom[$j] = sqrt($denom[$j]);
        }
        $out = [];
        foreach ($matrix as $id => $row) {
            $out[$id] = [];
            for ($j = 0; $j < $p; $j++) {
                $out[$id][$j] = $denom[$j] > 0 ? ((float) $row[$j]) / $denom[$j] : 0.0;
            }
        }
        return $out;
    }

    /** Min-max normalization: r_ij = (f_ij - f_j_min) / (f_j_max - f_j_min). */
    public function minMax(array $matrix): array
    {
        if (empty($matrix)) {
            return [];
        }
        $p = count(reset($matrix));
        $mins = array_fill(0, $p, PHP_FLOAT_MAX);
        $maxs = array_fill(0, $p, -PHP_FLOAT_MAX);
        foreach ($matrix as $row) {
            for ($j = 0; $j < $p; $j++) {
                $v = (float) ($row[$j] ?? 0);
                if ($v < $mins[$j]) { $mins[$j] = $v; }
                if ($v > $maxs[$j]) { $maxs[$j] = $v; }
            }
        }
        $out = [];
        foreach ($matrix as $id => $row) {
            $out[$id] = [];
            for ($j = 0; $j < $p; $j++) {
                $range = $maxs[$j] - $mins[$j];
                $out[$id][$j] = $range > 0 ? (((float) $row[$j]) - $mins[$j]) / $range : 0.5;
            }
        }
        return $out;
    }

    /** Z-score normalization: r_ij = (f_ij - mu_j) / sigma_j. */
    public function zScore(array $matrix): array
    {
        if (empty($matrix)) {
            return [];
        }
        $p = count(reset($matrix));
        $n = count($matrix);
        $mu = array_fill(0, $p, 0.0);
        foreach ($matrix as $row) {
            for ($j = 0; $j < $p; $j++) {
                $mu[$j] += (float) ($row[$j] ?? 0);
            }
        }
        for ($j = 0; $j < $p; $j++) {
            $mu[$j] /= $n;
        }
        $sigma = array_fill(0, $p, 0.0);
        foreach ($matrix as $row) {
            for ($j = 0; $j < $p; $j++) {
                $sigma[$j] += (((float) ($row[$j] ?? 0)) - $mu[$j]) ** 2;
            }
        }
        for ($j = 0; $j < $p; $j++) {
            $sigma[$j] = sqrt($sigma[$j] / $n);
        }
        $out = [];
        foreach ($matrix as $id => $row) {
            $out[$id] = [];
            for ($j = 0; $j < $p; $j++) {
                $out[$id][$j] = $sigma[$j] > 0 ? (((float) $row[$j]) - $mu[$j]) / $sigma[$j] : 0.0;
            }
        }
        return $out;
    }

    /** Compute Spearman rank-correlation coefficient between two rankings. */
    public function spearman(array $rank1, array $rank2): float
    {
        $n = count($rank1);
        if ($n < 2) {
            return 1.0;
        }
        $sum = 0.0;
        foreach ($rank1 as $id => $r1) {
            $r2 = $rank2[$id] ?? 0;
            $sum += ($r1 - $r2) ** 2;
        }
        return 1.0 - (6.0 * $sum) / ($n * ($n * $n - 1));
    }

    /** Compute all 3 normalizations at once. */
    public function all(array $matrix): array
    {
        return [
            'vector' => $this->vector($matrix),
            'minmax' => $this->minMax($matrix),
            'zscore' => $this->zScore($matrix),
        ];
    }
}
