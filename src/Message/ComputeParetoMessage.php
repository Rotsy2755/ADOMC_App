<?php

declare(strict_types=1);

namespace App\Message;

/** Async request to compute the Pareto front for a given problem. */
final class ComputeParetoMessage
{
    public function __construct(
        public readonly int $problemId,
        public readonly string $algorithm = 'epsilon', // 'epsilon' | 'nsga2'
    ) {
    }
}
