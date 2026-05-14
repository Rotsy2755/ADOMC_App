<?php

namespace App\Service\Mcdm;

/**
 * VIKOR — compromise ranking with S, R, Q indicators.
 *
 * Objectives are maximized.
 *   S_i = Σ_j λ_j · (A+_j − r_ij) / (A+_j − A−_j)
 *   R_i = max_j [λ_j · (A+_j − r_ij) / (A+_j − A−_j)]
 *   Q_i = ν·(S_i−S+)/(S−−S+) + (1−ν)·(R_i−R+)/(R−−R+)
 */
final class VikorService implements McdmMethodInterface
{
    public function getName(): string { return 'vikor'; }

    public function compute(array $normalizedMatrix, array $weights, array $params = []): array
    {
        $nu = (float) ($params['nu'] ?? 0.5);
        if (empty($normalizedMatrix)) { return []; }
        $p = count($weights);

        $aPlus = array_fill(0, $p, -PHP_FLOAT_MAX);
        $aMinus = array_fill(0, $p, PHP_FLOAT_MAX);
        foreach ($normalizedMatrix as $row) {
            for ($j = 0; $j < $p; $j++) {
                $v = (float) ($row[$j] ?? 0);
                if ($v > $aPlus[$j]) { $aPlus[$j] = $v; }
                if ($v < $aMinus[$j]) { $aMinus[$j] = $v; }
            }
        }

        $S = []; $R = [];
        foreach ($normalizedMatrix as $id => $row) {
            $s = 0.0; $r = 0.0;
            for ($j = 0; $j < $p; $j++) {
                $range = $aPlus[$j] - $aMinus[$j];
                $term = $range > 0 ? ((float) $weights[$j]) * ($aPlus[$j] - (float) $row[$j]) / $range : 0.0;
                $s += $term;
                if ($term > $r) { $r = $term; }
            }
            $S[$id] = $s; $R[$id] = $r;
        }

        $sPlus = min($S); $sMinus = max($S);
        $rPlus = min($R); $rMinus = max($R);

        $Q = [];
        foreach ($S as $id => $s) {
            $qs = ($sMinus - $sPlus) > 0 ? ($s - $sPlus) / ($sMinus - $sPlus) : 0.0;
            $qr = ($rMinus - $rPlus) > 0 ? ($R[$id] - $rPlus) / ($rMinus - $rPlus) : 0.0;
            $Q[$id] = $nu * $qs + (1 - $nu) * $qr;
        }

        asort($Q); // Lower Q is better.
        $out = [];
        $rank = 1;
        foreach ($Q as $id => $q) {
            $out[] = ['solution_id' => $id, 'score' => $q, 'rank' => $rank++, 'S' => $S[$id], 'R' => $R[$id]];
        }
        return $out;
    }

    public function getDefaultParams(): array { return ['nu' => 0.5]; }
}
