<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\SensitivityAnalysis;
use App\Message\ComputeSensitivityMessage;
use App\Repository\McdmResultRepository;
use App\Repository\ProblemRepository;
use App\Service\Mcdm\McdmRegistry;
use App\Service\Sensitivity\SensitivityAnalysisService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ComputeSensitivityHandler
{
    public function __construct(
        private readonly ProblemRepository $problems,
        private readonly McdmResultRepository $mcdmResults,
        private readonly McdmRegistry $registry,
        private readonly SensitivityAnalysisService $sensitivity,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ComputeSensitivityMessage $msg): void
    {
        $problem = $this->problems->find($msg->problemId);
        $mcdm    = $this->mcdmResults->find($msg->mcdmResultId);
        if ($problem === null || $mcdm === null) {
            return;
        }

        try {
            $solutions = [];
            foreach ($problem->getSolutions() as $s) {
                if ($s->isPareto()) {
                    $solutions[(int)$s->getId()] = $s->getObjectiveValues();
                }
            }
            if ($solutions === []) {
                return;
            }

            $criterion = (int)($msg->range['criterion'] ?? 0);
            $min  = (float)($msg->range['min']  ?? 0.1);
            $max  = (float)($msg->range['max']  ?? 0.9);
            $step = (float)($msg->range['step'] ?? 0.05);

            $method = $this->registry->get($mcdm->getMethod());
            $result = $this->sensitivity->run(
                $solutions,
                $mcdm->getWeights(),
                $criterion,
                $min,
                $max,
                $step,
                $method,
                $mcdm->getNormalization(),
                3,
                0.8,
                $mcdm->getParams(),
            );

            $entity = (new SensitivityAnalysis())
                ->setCriterion($criterion)
                ->setMinValue($min)
                ->setMaxValue($max)
                ->setStep($step)
                ->setConfigurations($result['configurations'])
                ->setRobustSolutions($result['robust'])
                ->setSummary($result['summary'])
                ->setMcdmResult($mcdm);

            $this->em->persist($entity);
            $this->em->flush();
        } catch (\Throwable $e) {
            $this->logger->error('Sensitivity computation failed: '.$e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }
}
