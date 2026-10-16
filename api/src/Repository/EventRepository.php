<?php

namespace App\Repository;

use App\Entity\Event;
use App\Enum\EventStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Event> */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    public function findOneByJoinCode(string $code): ?Event
    {
        return $this->findOneBy(['joinCode' => strtoupper(trim($code))]);
    }

    public function joinCodeExists(string $code): bool
    {
        return null !== $this->findOneByJoinCode($code);
    }

    /** @return list<Event> */
    public function findPlayable(): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.status IN (:statuses)')
            ->setParameter('statuses', [EventStatus::Live, EventStatus::Paused])
            ->orderBy('e.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
