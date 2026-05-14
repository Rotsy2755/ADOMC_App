<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Problem;
use App\Repository\AhpMatrixRepository;
use App\Repository\McdmResultRepository;
use App\Repository\SensitivityAnalysisRepository;
use App\Security\Voter\ProblemVoter;
use App\Service\Audit\AuditLogger;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/export')]
#[IsGranted('ROLE_USER')]
final class ExportController extends AbstractController
{
    #[Route('/pdf/{id}', name: 'app_export_pdf', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function pdf(
        Problem $problem,
        AhpMatrixRepository $matrices,
        McdmResultRepository $mcdmResults,
        SensitivityAnalysisRepository $sensitivities,
        AuditLogger $audit,
    ): Response {
        $this->denyAccessUnlessGranted(ProblemVoter::VIEW, $problem);

        $paretoSolutions = [];
        foreach ($problem->getSolutions() as $s) {
            if ($s->isPareto()) {
                $paretoSolutions[] = $s;
            }
        }
        $ahpList   = $matrices->findBy(['problem' => $problem], ['createdAt' => 'DESC']);
        $mcdmList  = $mcdmResults->findBy(['problem' => $problem], ['createdAt' => 'DESC']);
        $sensList  = [];
        foreach ($mcdmList as $m) {
            foreach ($sensitivities->findBy(['mcdmResult' => $m], ['createdAt' => 'DESC']) as $a) {
                $sensList[] = $a;
            }
        }

        $html = $this->renderView('export/pdf.html.twig', [
            'problem'   => $problem,
            'solutions' => $paretoSolutions,
            'ahpList'   => $ahpList,
            'mcdmList'  => $mcdmList,
            'sensList'  => $sensList,
            'generatedAt' => new \DateTimeImmutable(),
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $audit->log('export.pdf', 'Problem', $problem->getId());

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => sprintf('attachment; filename="ADOMC-%d.pdf"', $problem->getId()),
            ],
        );
    }

    #[Route('/csv/{id}', name: 'app_export_csv', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function csv(Problem $problem, AuditLogger $audit): Response
    {
        $this->denyAccessUnlessGranted(ProblemVoter::VIEW, $problem);

        $rows = [];
        $objectives = $problem->getObjectives();
        $header = ['solution_id', 'total_weight'];
        foreach ($objectives as $idx => $label) {
            $header[] = 'f'.($idx + 1).' ('.$label.')';
        }
        $header[] = 'selected_items';
        $rows[] = $header;

        foreach ($problem->getSolutions() as $s) {
            if (!$s->isPareto()) {
                continue;
            }
            $row = [$s->getId(), $s->getTotalWeight()];
            foreach ($s->getObjectiveValues() as $v) {
                $row[] = $v;
            }
            $row[] = implode('|', $s->getSelectedItemIds());
            $rows[] = $row;
        }

        $buf = fopen('php://temp', 'r+');
        foreach ($rows as $r) {
            fputcsv($buf, $r, ';');
        }
        rewind($buf);
        $csv = stream_get_contents($buf);
        fclose($buf);

        $audit->log('export.csv', 'Problem', $problem->getId());
        return new Response(
            (string)$csv,
            200,
            [
                'Content-Type'        => 'text/csv; charset=UTF-8',
                'Content-Disposition' => sprintf('attachment; filename="ADOMC-%d.csv"', $problem->getId()),
            ],
        );
    }

    #[Route('/json/{id}', name: 'app_export_json', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function exportJson(Problem $problem, AuditLogger $audit): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProblemVoter::VIEW, $problem);
        $payload = [
            'problem' => [
                'id'        => $problem->getId(),
                'name'      => $problem->getName(),
                'capacity'  => $problem->getCapacity(),
                'objectives' => $problem->getObjectives(),
                'status'    => $problem->getStatus(),
            ],
            'items' => array_map(static fn($i) => [
                'id'     => $i->getId(),
                'name'   => $i->getName(),
                'weight' => $i->getWeight(),
                'values' => $i->getValues(),
            ], $problem->getItems()->toArray()),
            'pareto' => array_values(array_map(static fn($s) => [
                'id'             => $s->getId(),
                'total_weight'   => $s->getTotalWeight(),
                'objectives'     => $s->getObjectiveValues(),
                'selected_items' => $s->getSelectedItemIds(),
            ], array_filter($problem->getSolutions()->toArray(), static fn($s) => $s->isPareto()))),
        ];
        $audit->log('export.json', 'Problem', $problem->getId());
        return new JsonResponse($payload);
    }
}
