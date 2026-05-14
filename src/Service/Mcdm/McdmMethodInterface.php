<?php

namespace App\Service\Mcdm;

/** Contract for MCDM methods. */
interface McdmMethodInterface
{
    public function getName(): string;

    /**
     * @param array<int|string, float[]> $normalizedMatrix  [solution_id => [f1..fp]]
     * @param float[]                     $weights           [λ1..λp]
     * @param array<string,mixed>         $params
     * @return list<array{solution_id:int|string,score:float,rank:int}>
     */
    public function compute(array $normalizedMatrix, array $weights, array $params = []): array;

    /** @return array<string,mixed> */
    public function getDefaultParams(): array;
}
