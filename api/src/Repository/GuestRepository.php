<?php

namespace App\Repository;

use App\Entity\Event;
use App\Entity\Guest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;

/** @extends ServiceEntityRepository<Guest> */
class GuestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Guest::class);
    }

    public function findOneBySessionToken(string $token): ?Guest
    {
        return $this->findOneBy(['sessionToken' => $token]);
    }

    public function countForEvent(Event $event): int
    {
        return $this->count(['event' => $event]);
    }

    /** Guests seen in the last few minutes, used as the "live" audience counter. */
    public function countActive(Event $event, \DateTimeImmutable $since): int
    {
        $count = $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->andWhere('g.event = :event')
            ->andWhere('g.lastSeenAt >= :since')
            ->andWhere('g.banned = false')
            ->setParameter('event', $event->getId(), UuidType::NAME)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }
}
