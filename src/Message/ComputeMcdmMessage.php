<?php

declare(strict_types=1);

namespace App\Message;

/** Async request to compute an MCDM method for a problem + scenario. */
final class ComputeMcdmMessage
{
    public function __construct(
        public readonly int $problemId,
        public readonly int $ahpMatrixId,
        public readonly string $method,
        public readonly string $normalization = 'vector',
        public readonly array $params = [],
    ) {
    }
}
