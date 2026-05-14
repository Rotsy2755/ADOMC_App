<?php

namespace App\Service\Mcdm;

/**
 * PROMETHEE II — net outranking flow Φ(a) = Φ⁺(a) − Φ⁻(a).
 *
 * Preference functions supported (per criterion):
 *   - usual:         P = 1 if d > 0 else 0
 *   - quasi:         P = 1 if d > q else 0 (needs q)
 *   - linear:        P = d/p if d <= p else 1 (needs p)
 *   - level:         P = 0 if d<=q, 0.5 if q<d<=p, 1 if d>p
 *   - linear-ind:    P = 0 if d<=q, (d-q)/(p-q) if q<d<=p, 1 if d>p
 *   - gaussian:      P = 1 - exp(-d^2 / (2σ²))
 */
final class PrometheeIIService implements McdmMethodInterface
{
    public function getName(): string { return 'promethee2'; }

    public function compute(array $normalizedMatrix, array $weights, array $params = []): array
    {
        if (empty($normalizedMatrix)) { return []; }
        $p = count($weights);
        $types = $params['types'] ?? array_fill(0, $p, 'usual');
        $thresholds = $params['thresholds'] ?? array_fill(0, $p, ['q' => 0.0, 'p' => 0.5, 'sigma' => 0.3]);

        $ids = array_keys($normalizedMatrix);
        $m = count($ids);

        // π(a,b) = Σ_k λ_k · P_k(a,b)
        $pi = [];
        foreach ($ids as $a) {
            foreach ($ids as $b) {
                if ($a === $b) { $pi[$a][$b] = 0.0; continue; }
                $sum = 0.0;
                for ($k = 0; $k < $p; $k++) {
                    $d = ((float) $normalizedMatrix[$a][$k]) - ((float) $normalizedMatrix[$b][$k]);
                    $sum += ((float) $weights[$k]) * $this->preference($d, $types[$k] ?? 'usual', $thresholds[$k] ?? []);
                }
                $pi[$a][$b] = $sum;
            }
        }

        // Φ⁺ / Φ⁻ / Φ
        $scores = [];
        foreach ($ids as $a) {
            $phiPlus = 0.0; $phiMinus = 0.0;
            foreach ($ids as $b) {
                if ($a === $b) { continue; }
                $phiPlus += $pi[$a][$b];
                $phiMinus += $pi[$b][$a];
            }
            $div = max($m - 1, 1);
            $scores[$a] = ($phiPlus - $phiMinus) / $div;
        }

        arsort($scores);
        $out = [];
        $rank = 1;
        foreach ($scores as $id => $s) {
            $out[] = ['solution_id' => $id, 'score' => $s, 'rank' => $rank++];
        }
        return $out;
    }

    private function preference(float $d, string $type, array $t): float
    {
        if ($d <= 0) { return 0.0; }
        $q = (float) ($t['q'] ?? 0.0);
        $p = (float) ($t['p'] ?? 0.5);
        $sigma = (float) ($t['sigma'] ?? 0.3);
        return match ($type) {
            'quasi' => $d > $q ? 1.0 : 0.0,
            'linear' => $d <= $p ? ($p > 0 ? $d / $p : 1.0) : 1.0,
            'level' => $d <= $q ? 0.0 : ($d <= $p ? 0.5 : 1.0),
            'linear-ind' => $d <= $q ? 0.0 : ($d <= $p ? (($p - $q) > 0 ? ($d - $q) / ($p - $q) : 1.0) : 1.0),
            'gaussian' => 1.0 - exp(-($d * $d) / (2 * max($sigma, 1e-9) ** 2)),
            default => 1.0,
        };
    }

    public function getDefaultParams(): array { return ['types' => [], 'thresholds' => []]; }
}
