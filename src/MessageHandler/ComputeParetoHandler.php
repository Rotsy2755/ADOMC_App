<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Problem;
use App\Entity\Solution;
use App\Message\ComputeParetoMessage;
use App\Message\SendReportEmailMessage;
use App\Repository\ProblemRepository;
use App\Service\Fuzzy\FuzzyKnapsackService;
use App\Service\Pareto\EpsilonConstraintSolver;
use App\Service\Pareto\NsgaIISolver;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class ComputeParetoHandler
{
    public function __construct(
        private readonly ProblemRepository $problems,
        private readonly EntityManagerInterface $em,
        private readonly EpsilonConstraintSolver $epsilon,
        private readonly NsgaIISolver $nsga,
        private readonly FuzzyKnapsackService $fuzzy,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ComputeParetoMessage $msg): void
    {
        $problem = $this->problems->find($msg->problemId);
        if ($problem === null) {
            return;
        }

        $problem->setStatus(Problem::STATUS_COMPUTING);
        $problem->setProgress(0);
        $this->em->flush();

        try {
            // Clear any previous solutions.
            foreach ($problem->getSolutions() as $s) {
                $this->em->remove($s);
            }
            $this->em->flush();

            $algo = $msg->algorithm;
            $n = $problem->getItems()->count();

            if ($algo === 'nsga2' || $n > 40) {
                $this->runNsga($problem);
            } else {
                $this->runEpsilon($problem);
            }

            $problem->setStatus(Problem::STATUS_DONE);
            $problem->setProgress(100);
            $problem->touch();
            $this->em->flush();

            // Notify owner by e-mail for long runs.
            if ($n > 40 && $problem->getProject()?->getUser() !== null) {
                $this->bus->dispatch(new SendReportEmailMessage(
                    userId:    (int)$problem->getProject()->getUser()->getId(),
                    problemId: (int)$problem->getId(),
                    subject:   sprintf('[ADOMC] Pareto front ready — %s', $problem->getName()),
                    body:      'Your Pareto front computation completed successfully. Open ADOMC to review the solutions.'
                ));
            }
        } catch (\Throwable $e) {
            $problem->setStatus(Problem::STATUS_FAILED);
            $this->em->flush();
            $this->logger->error('Pareto computation failed: '.$e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }

    private function runEpsilon(Problem $problem): void
    {
        if ($problem->isFuzzyEnabled()) {
            // Defuzzify objectives before solving.
            foreach ($problem->getItems() as $item) {
                $fuzzy = $item->getFuzzyValues();
                if ($fuzzy !== null) {
                    $crisp = [];
                    foreach ($fuzzy as $tfn) {
                        $crisp[] = $this->fuzzy->defuzzifyCentroid($tfn);
                    }
                    $item->setValues($crisp);
                }
            }
            $this->em->flush();
        }

        $front = $this->epsilon->solve($problem);
        $problem->setAlgorithm('epsilon');

        foreach ($front as $entry) {
            $sol = (new Solution())
                ->setSelectedItemIds($entry['items'])
                ->setTotalWeight((float)$entry['weight'])
                ->setObjectiveValues($entry['values'])
                ->setIsPareto(true)
                ->setAlgorithm('epsilon')
                ->setProblem($problem);
            $this->em->persist($sol);
        }
    }

    private function runNsga(Problem $problem): void
    {
        $problem->setAlgorithm('nsga2');
        $front = $this->nsga->solve($problem, function (int $gen, int $total) use ($problem): void {
            $percent = $total > 0 ? (int)floor(99 * $gen / $total) : 0;
            $problem->setProgress(min(99, max(0, $percent)));
            $this->em->flush();
        });

        foreach ($front as $entry) {
            $sol = (new Solution())
                ->setSelectedItemIds($entry['items'])
                ->setTotalWeight((float)$entry['weight'])
                ->setObjectiveValues($entry['values'])
                ->setIsPareto(true)
                ->setAlgorithm('nsga2')
                ->setProblem($problem);
            $this->em->persist($sol);
        }
    }
}
