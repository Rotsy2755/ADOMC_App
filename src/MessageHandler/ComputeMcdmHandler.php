<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\McdmResult;
use App\Message\ComputeMcdmMessage;
use App\Repository\AhpMatrixRepository;
use App\Repository\ProblemRepository;
use App\Service\Mcdm\McdmRegistry;
use App\Service\Normalization\NormalizationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ComputeMcdmHandler
{
    public function __construct(
        private readonly ProblemRepository $problems,
        private readonly AhpMatrixRepository $matrices,
        private readonly McdmRegistry $registry,
        private readonly NormalizationService $norm,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ComputeMcdmMessage $msg): void
    {
        $problem = $this->problems->find($msg->problemId);
        $matrix  = $this->matrices->find($msg->ahpMatrixId);
        if ($problem === null || $matrix === null) {
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

            $normalized = match ($msg->normalization) {
                'minmax' => $this->norm->minMax($solutions),
                'zscore' => $this->norm->zScore($solutions),
                default  => $this->norm->vector($solutions),
            };

            $method = $this->registry->get($msg->method);
            $rankings = $method->compute($normalized, $matrix->getWeights(), $msg->params);

            $recommendedId = null;
            foreach ($rankings as $r) {
                if ((int)$r['rank'] === 1) {
                    $recommendedId = (int)$r['solution_id'];
                    break;
                }
            }

            $result = (new McdmResult())
                ->setMethod($msg->method)
                ->setNormalization($msg->normalization)
                ->setRankings($rankings)
                ->setParams($msg->params)
                ->setWeights($matrix->getWeights())
                ->setRecommendedSolutionId($recommendedId)
                ->setProblem($problem)
                ->setAhpMatrix($matrix);

            $this->em->persist($result);
            $this->em->flush();
        } catch (\Throwable $e) {
            $this->logger->error('MCDM computation failed: '.$e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }
}
