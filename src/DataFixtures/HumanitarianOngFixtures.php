<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\AhpMatrix;
use App\Entity\Item;
use App\Entity\Problem;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Reference test set — the exact humanitarian-NGO MOKP problem from the
 * source document.
 *
 * Vector normalization reference: sqrt(9²+10²+8²+6²+7²+9²+5²+6²) = sqrt(472) ≈ 21.732
 * AHP reference weights: λ = [0.50, 0.30, 0.20], CR < 0.10
 * Expected Pareto front: at least 5 non-dominated solutions via ε-constraint.
 */
final class HumanitarianOngFixtures extends Fixture
{
    /** Eight supply items — columns: weight (kg), f1 beneficiaries, f2 urgency, f3 feasibility */
    private const ONG_ITEMS = [
        ['name' => 'Medicines',          'w' =>  8, 'f1' =>  9, 'f2' => 10, 'f3' =>  8],
        ['name' => 'Food rations',       'w' => 12, 'f1' => 10, 'f2' =>  8, 'f3' =>  9],
        ['name' => 'Water (500 L)',      'w' => 15, 'f1' =>  8, 'f2' =>  9, 'f3' =>  7],
        ['name' => 'Blankets',           'w' =>  5, 'f1' =>  6, 'f2' =>  6, 'f3' => 10],
        ['name' => 'Tents',              'w' => 10, 'f1' =>  7, 'f2' =>  7, 'f3' =>  8],
        ['name' => 'Hygiene kits',       'w' =>  7, 'f1' =>  9, 'f2' =>  8, 'f3' =>  9],
        ['name' => 'Radio (comm.)',      'w' =>  3, 'f1' =>  5, 'f2' =>  7, 'f3' =>  6],
        ['name' => 'Generator',          'w' =>  9, 'f1' =>  6, 'f2' =>  5, 'f3' =>  7],
    ];

    /** Reference AHP pairwise matrix — geometric-mean weights ≈ [0.50, 0.30, 0.20], CR < 0.10. */
    private const ONG_AHP = [
        [1.0,    2.0,    3.0],
        [0.5,    1.0,    2.0],
        [1 / 3,  0.5,    1.0],
    ];

    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        // Admin account
        $admin = new User();
        $admin->setEmail('admin@adomc.local')
            ->setFullName('ADOMC Admin')
            ->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->hasher->hashPassword($admin, 'Admin1234!'));
        $manager->persist($admin);

        // Decision-maker account (owner of the ONG reference case)
        $decider = new User();
        $decider->setEmail('ong@adomc.local')
            ->setFullName('ONG Decision Maker')
            ->setRoles(['ROLE_USER']);
        $decider->setPassword($this->hasher->hashPassword($decider, 'User1234!'));
        $manager->persist($decider);

        // Reference project & problem
        $project = new Project();
        $project->setName('Humanitarian ONG — Reference Case')
            ->setDescription('Reference MOKP problem from the specification document.')
            ->setUser($decider);
        $manager->persist($project);

        $problem = new Problem();
        $problem->setName('ONG supplies — capacity 30 kg')
            ->setDescription('Select humanitarian supplies to maximize beneficiaries (f1), urgency (f2) and feasibility (f3) under a 30 kg airlift capacity.')
            ->setCapacity(30.0)
            ->setObjectiveCount(3)
            ->setObjectives(['f1' => 'Beneficiaries', 'f2' => 'Urgency', 'f3' => 'Feasibility'])
            ->setAlgorithm('epsilon')
            ->setFuzzyEnabled(false)
            ->setStatus(Problem::STATUS_DRAFT)
            ->setProject($project);
        $manager->persist($problem);

        foreach (self::ONG_ITEMS as $pos => $row) {
            $item = new Item();
            $item->setName($row['name'])
                ->setWeight((float) $row['w'])
                ->setValues([(float) $row['f1'], (float) $row['f2'], (float) $row['f3']])
                ->setPosition($pos)
                ->setProblem($problem);
            $manager->persist($item);
        }

        // Reference AHP matrix — λ = [0.50, 0.30, 0.20], CR < 0.10
        $ahp = new AhpMatrix();
        $ahp->setLabel('Reference preference structure')
            ->setMatrix(self::ONG_AHP)
            ->setWeights([0.5396, 0.2970, 0.1634])
            ->setLambdaMax(3.0092)
            ->setCi(0.0046)
            ->setCr(0.0079)
            ->setConsistent(true)
            ->setProblem($problem);
        $manager->persist($ahp);

        // Three synthetic additional cases to widen the seed database
        $this->loadSyntheticCase(
            $manager,
            $decider,
            'Synthetic #1 — Urban logistics',
            'Minimum-weight parcels routing, 20 kg capacity, 2 objectives (delay, cost).',
            20.0,
            2,
            ['f1' => 'Delay (-)', 'f2' => 'Cost (-)'],
            [
                ['A', 2, 4, 3],
                ['B', 3, 5, 4],
                ['C', 4, 7, 5],
                ['D', 5, 6, 8],
                ['E', 6, 8, 6],
                ['F', 7, 9, 7],
            ]
        );

        $this->loadSyntheticCase(
            $manager,
            $decider,
            'Synthetic #2 — School equipment',
            'Educational kit distribution, 25 kg capacity, 3 objectives (impact, coverage, durability).',
            25.0,
            3,
            ['f1' => 'Impact', 'f2' => 'Coverage', 'f3' => 'Durability'],
            [
                ['Books',   4,  8,  9,  5],
                ['Tablets', 3,  9,  7,  8],
                ['Desks',   9,  7,  8, 10],
                ['Boards',  6,  6,  7,  9],
                ['Kits',    5,  8,  9,  6],
                ['Lamps',   2,  5,  6,  7],
                ['Chairs',  3,  6,  8,  9],
            ]
        );

        $this->loadSyntheticCase(
            $manager,
            $decider,
            'Synthetic #3 — Health campaign',
            'Vaccine logistics under 18 kg cold-chain constraint, 2 objectives (coverage, urgency).',
            18.0,
            2,
            ['f1' => 'Coverage', 'f2' => 'Urgency'],
            [
                ['Vaccines A', 4, 10, 9],
                ['Vaccines B', 3,  8, 9],
                ['Syringes',   2,  7, 6],
                ['Ice packs',  5,  6, 8],
                ['Coolers',    6,  7, 9],
                ['Forms',      1,  5, 4],
            ]
        );

        $manager->flush();
    }

    private function loadSyntheticCase(
        ObjectManager $manager,
        User $user,
        string $name,
        string $description,
        float $capacity,
        int $p,
        array $objectives,
        array $rows
    ): void {
        $project = new Project();
        $project->setName($name)
            ->setDescription($description)
            ->setUser($user);
        $manager->persist($project);

        $problem = new Problem();
        $problem->setName($name . ' — problem')
            ->setDescription($description)
            ->setCapacity($capacity)
            ->setObjectiveCount($p)
            ->setObjectives($objectives)
            ->setAlgorithm('epsilon')
            ->setStatus(Problem::STATUS_DRAFT)
            ->setProject($project);
        $manager->persist($problem);

        foreach ($rows as $pos => $row) {
            $item = new Item();
            $item->setName((string) $row[0])
                ->setWeight((float) $row[1])
                ->setValues(array_map('floatval', array_slice($row, 2, $p)))
                ->setPosition($pos)
                ->setProblem($problem);
            $manager->persist($item);
        }
    }
}
