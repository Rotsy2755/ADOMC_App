<?php

namespace App\Service\Mcdm;

/**
 * ELECTRE I — concordance / discordance indices and outranking kernel.
 *
 * c_ab = Σ_{k : f_a^k ≥ f_b^k} λ_k
 * d_ab = max_k (f_b^k − f_a^k)/(A+_k − A−_k)
 * a S b if c_ab ≥ c* and d_ab ≤ d*.
 *
 * Score is the net outranking count (|{b : aSb}| − |{b : bSa}|).
 */
final class ElectreIService implements McdmMethodInterface
{
    public function getName(): string { return 'electre1'; }

    public function compute(array $normalizedMatrix, array $weights, array $params = []): array
    {
        $cStar = (float) ($params['c'] ?? 0.65);
        $dStar = (float) ($params['d'] ?? 0.35);
        if (empty($normalizedMatrix)) { return []; }
        $p = count($weights);
        $ids = array_keys($normalizedMatrix);

        $aPlus = array_fill(0, $p, -PHP_FLOAT_MAX);
        $aMinus = array_fill(0, $p, PHP_FLOAT_MAX);
        foreach ($normalizedMatrix as $row) {
            for ($k = 0; $k < $p; $k++) {
                $v = (float) ($row[$k] ?? 0);
                if ($v > $aPlus[$k]) { $aPlus[$k] = $v; }
                if ($v < $aMinus[$k]) { $aMinus[$k] = $v; }
            }
        }

        $outranks = [];
        foreach ($ids as $a) {
            $outranks[$a] = [];
            foreach ($ids as $b) {
                if ($a === $b) { continue; }
                $c = 0.0;
                $d = 0.0;
                for ($k = 0; $k < $p; $k++) {
                    $fa = (float) $normalizedMatrix[$a][$k];
                    $fb = (float) $normalizedMatrix[$b][$k];
                    if ($fa >= $fb) {
                        $c += (float) $weights[$k];
                    } else {
                        $range = $aPlus[$k] - $aMinus[$k];
                        $dk = $range > 0 ? ($fb - $fa) / $range : 0.0;
                        if ($dk > $d) { $d = $dk; }
                    }
                }
                if ($c >= $cStar && $d <= $dStar) {
                    $outranks[$a][$b] = true;
                }
            }
        }

        $scores = [];
        foreach ($ids as $a) {
            $out = count($outranks[$a] ?? []);
            $in = 0;
            foreach ($ids as $b) {
                if (!empty($outranks[$b][$a])) { $in++; }
            }
            $scores[$a] = $out - $in;
        }

        arsort($scores);
        $result = [];
        $rank = 1;
        foreach ($scores as $id => $s) {
            $result[] = ['solution_id' => $id, 'score' => (float) $s, 'rank' => $rank++, 'kernel' => !$this->isOutranked($id, $outranks, $ids)];
        }
        return $result;
    }

    private function isOutranked(int|string $a, array $outranks, array $ids): bool
    {
        foreach ($ids as $b) {
            if ($b === $a) { continue; }
            if (!empty($outranks[$b][$a])) { return true; }
        }
        return false;
    }

    public function getDefaultParams(): array { return ['c' => 0.65, 'd' => 0.35]; }
}
