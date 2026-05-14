<?php

namespace App\Service\Mcdm;

final class McdmRegistry
{
    /** @var array<string, McdmMethodInterface> */
    private array $methods = [];

    public function __construct(
        TopsisService $topsis,
        VikorService $vikor,
        PrometheeIIService $promethee,
        ElectreIService $electre,
    ) {
        $this->methods[$topsis->getName()] = $topsis;
        $this->methods[$vikor->getName()] = $vikor;
        $this->methods[$promethee->getName()] = $promethee;
        $this->methods[$electre->getName()] = $electre;
    }

    public function get(string $name): McdmMethodInterface
    {
        if (!isset($this->methods[$name])) {
            throw new \InvalidArgumentException(sprintf('Unknown MCDM method "%s".', $name));
        }
        return $this->methods[$name];
    }

    /** @return array<string, McdmMethodInterface> */
    public function all(): array { return $this->methods; }
}
