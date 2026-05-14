<?php

namespace App\Service\Mcdm;

/** TOPSIS — weighted normalized matrix, distances to ideal/anti-ideal, closeness coefficient. */
final class TopsisService implements McdmMethodInterface
{
    public function getName(): string { return 'topsis'; }

    public function compute(array $normalizedMatrix, array $weights, array $params = []): array
    {
        if (empty($normalizedMatrix)) {
            return [];
        }
        $p = count($weights);
        $ids = array_keys($normalizedMatrix);

        // Weighted matrix v_ij = λ_j · r_ij
        $v = [];
        foreach ($normalizedMatrix as $id => $row) {
            $v[$id] = [];
            for ($j = 0; $j < $p; $j++) {
                $v[$id][$j] = ((float) ($row[$j] ?? 0)) * ((float) ($weights[$j] ?? 0));
            }
        }

        // Ideal A+ (max) / anti-ideal A- (min) per criterion. (Objectives are to be maximized.)
        $aPlus = array_fill(0, $p, -PHP_FLOAT_MAX);
        $aMinus = array_fill(0, $p, PHP_FLOAT_MAX);
        foreach ($v as $row) {
            for ($j = 0; $j < $p; $j++) {
                if ($row[$j] > $aPlus[$j]) { $aPlus[$j] = $row[$j]; }
                if ($row[$j] < $aMinus[$j]) { $aMinus[$j] = $row[$j]; }
            }
        }

        // Distances and closeness
        $scores = [];
        foreach ($v as $id => $row) {
            $dp = 0.0; $dm = 0.0;
            for ($j = 0; $j < $p; $j++) {
                $dp += ($row[$j] - $aPlus[$j]) ** 2;
                $dm += ($row[$j] - $aMinus[$j]) ** 2;
            }
            $dp = sqrt($dp); $dm = sqrt($dm);
            $scores[$id] = ($dp + $dm) > 0 ? $dm / ($dp + $dm) : 0.0;
        }

        arsort($scores);
        $out = [];
        $rank = 1;
        foreach ($scores as $id => $s) {
            $out[] = ['solution_id' => $id, 'score' => $s, 'rank' => $rank++];
        }
        return $out;
    }

    public function getDefaultParams(): array { return []; }
}
