<?php

namespace App\Service\Sensitivity;

use App\Service\Mcdm\McdmMethodInterface;
use App\Service\Normalization\NormalizationService;

/**
 * Sensitivity analysis on MCDM weights.
 *
 * For criterion k varying in [min, max] by step Δλ, redistribute the remaining weight
 * proportionally among other criteria, run the MCDM method, and track each solution's
 * rank. Solutions in the top-k of at least (threshold * totalConfigs) configurations
 * are labeled "robust".
 */
final class SensitivityAnalysisService
{
    public function __construct(private NormalizationService $norm) {}

    /**
     * @param array<int|string, float[]> $matrix        Raw objective matrix per solution
     * @param float[]                    $baseWeights   Base AHP weights (Σ = 1)
     * @param int                        $criterion     Index of the varied criterion
     * @return array{configurations: list<array<string,mixed>>, robust: list<array<string,mixed>>, summary: string}
     */
    public function run(
        array $matrix,
        array $baseWeights,
        int $criterion,
        float $min,
        float $max,
        float $step,
        McdmMethodInterface $method,
        string $normalization = 'vector',
        int $topK = 3,
        float $threshold = 0.8,
        array $params = [],
    ): array {
        if ($step <= 0 || $min > $max) {
            throw new \InvalidArgumentException('Invalid sensitivity interval.');
        }
        $normalized = $this->normalize($matrix, $normalization);
        $configs = [];
        $rankCountsTopK = [];   // solution_id => count of configs it is in top-k
        $rankTopKIntervals = [];// solution_id => list of lambda values where it was #1

        for ($lambda = $min; $lambda <= $max + 1e-9; $lambda += $step) {
            $lambdaK = round($lambda, 6);
            $weights = $this->redistributeWeights($baseWeights, $criterion, $lambdaK);
            $rankings = $method->compute($normalized, $weights, $params);

            $rankings = array_values($rankings);
            $configs[] = ['lambda_k' => $lambdaK, 'rankings' => $rankings];

            foreach ($rankings as $r) {
                $id = $r['solution_id'];
                if (!isset($rankCountsTopK[$id])) {
                    $rankCountsTopK[$id] = 0;
                    $rankTopKIntervals[$id] = [];
                }
                if ($r['rank'] <= $topK) {
                    $rankCountsTopK[$id]++;
                }
                if ($r['rank'] === 1) {
                    $rankTopKIntervals[$id][] = $lambdaK;
                }
            }
        }

        $total = count($configs);
        $robust = [];
        foreach ($rankCountsTopK as $id => $count) {
            $score = $total > 0 ? $count / $total : 0.0;
            if ($score >= $threshold) {
                $lambdas = $rankTopKIntervals[$id] ?? [];
                $robust[] = [
                    'solution_id' => $id,
                    'robustness'  => $score,
                    'top1_count'  => count($lambdas),
                    'dominant_interval' => $lambdas ? [min($lambdas), max($lambdas)] : null,
                ];
            }
        }
        usort($robust, fn($a, $b) => $b['robustness'] <=> $a['robustness']);

        $summary = $this->buildSummary($robust, $min, $max, $total);
        return ['configurations' => $configs, 'robust' => $robust, 'summary' => $summary];
    }

    private function redistributeWeights(array $base, int $k, float $newLambdaK): array
    {
        $p = count($base);
        $sumOthers = 0.0;
        for ($j = 0; $j < $p; $j++) {
            if ($j !== $k) { $sumOthers += $base[$j]; }
        }
        $out = [];
        for ($j = 0; $j < $p; $j++) {
            if ($j === $k) {
                $out[$j] = max(0.0, $newLambdaK);
            } else {
                $out[$j] = $sumOthers > 0 ? $base[$j] * (1.0 - $newLambdaK) / $sumOthers : 0.0;
            }
        }
        // Normalize to exactly 1 to mitigate rounding
        $s = array_sum($out);
        if ($s > 0) {
            foreach ($out as $j => $v) { $out[$j] = $v / $s; }
        }
        return $out;
    }

    private function normalize(array $matrix, string $method): array
    {
        return match ($method) {
            'minmax' => $this->norm->minMax($matrix),
            'zscore' => $this->norm->zScore($matrix),
            default  => $this->norm->vector($matrix),
        };
    }

    private function buildSummary(array $robust, float $min, float $max, int $total): string
    {
        if (empty($robust)) {
            return "Aucune solution n'est robuste sur l'intervalle [$min, $max] avec le seuil demandé.";
        }
        $best = $robust[0];
        $pct = number_format($best['robustness'] * 100, 1);
        $interval = $best['dominant_interval']
            ? sprintf('[%.2f, %.2f]', $best['dominant_interval'][0], $best['dominant_interval'][1])
            : 'aucune plage continue';
        return sprintf(
            "La solution #%s reste dans le top-k pour λ ∈ %s, représentant %s %% des configurations testées (sur %d).",
            (string) $best['solution_id'], $interval, $pct, $total
        );
    }
}
