<?php

namespace App\Repository;

use App\Entity\Guest;
use App\Entity\QueueItem;
use App\Entity\Vote;
use App\Enum\QueueItemStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;

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

    /** Downvotes of a guest on tracks still waiting in the queue. */
    public function countActiveDownvotes(Guest $guest): int
    {
        $count = $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->join('v.queueItem', 'item')
            ->andWhere('v.guest = :guest')
            ->andWhere('v.value < 0')
            ->andWhere('item.status = :status')
            ->setParameter('guest', $guest->getId(), UuidType::NAME)
            ->setParameter('status', QueueItemStatus::Pending)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
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
            // IDENTITY() would return the raw BINARY(16) column, a selected mapped field goes through UuidType
            ->select('item.id AS id', 'v.value')
            ->join('v.queueItem', 'item')
            ->andWhere('v.guest = :guest')
            ->andWhere('v.queueItem IN (:items)')
            ->setParameter('guest', $guest->getId(), UuidType::NAME)
            ->setParameter('items', array_map(static fn (QueueItem $item): string => $item->getId()->toBinary(), $items), ArrayParameterType::BINARY)
            ->getQuery()
            ->getArrayResult();

        $values = [];
        foreach ($rows as $row) {
            $values[(string) $row['id']] = (int) $row['value'];
        }

        return $values;
    }
}
