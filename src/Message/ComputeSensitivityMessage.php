<?php

declare(strict_types=1);

namespace App\Message;

/** Async request for a sensitivity analysis sweep. */
final class ComputeSensitivityMessage
{
    public function __construct(
        public readonly int $problemId,
        public readonly int $mcdmResultId,
        public readonly array $range = ['min' => 0.1, 'max' => 0.9, 'step' => 0.05],
    ) {
    }
}
