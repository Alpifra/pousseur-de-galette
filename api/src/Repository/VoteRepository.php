<?php

namespace App\Repository;

use App\Entity\Guest;
use App\Entity\QueueItem;
use App\Entity\Vote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Vote> */
class VoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vote::class);
    }

    public function findOneFor(QueueItem $item, Guest $guest): ?Vote
    {
        return $this->findOneBy(['queueItem' => $item, 'guest' => $guest]);
    }

    /**
     * Vote values of a guest, indexed by queue item id.
     *
     * @param list<QueueItem> $items
     *
     * @return array<string, int>
     */
    public function valuesForGuest(Guest $guest, array $items): array
    {
        if ([] === $items) {
            return [];
        }

        /** @var list<array{id: string, value: int}> $rows */
        $rows = $this->createQueryBuilder('v')
            ->select('IDENTITY(v.queueItem) AS id', 'v.value')
            ->andWhere('v.guest = :guest')
            ->andWhere('v.queueItem IN (:items)')
            ->setParameter('guest', $guest)
            ->setParameter('items', $items)
            ->getQuery()
            ->getArrayResult();

        $values = [];
        foreach ($rows as $row) {
            $values[(string) $row['id']] = (int) $row['value'];
        }

        return $values;
    }
}
