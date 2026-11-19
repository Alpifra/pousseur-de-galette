<?php

namespace App\Repository;

use App\Entity\Event;
use App\Entity\Guest;
use App\Entity\QueueItem;
use App\Enum\QueueItemStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;

/** @extends ServiceEntityRepository<QueueItem> */
class QueueItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QueueItem::class);
    }

    /**
     * The queue as the guests see it: pinned first, then by score, oldest proposal first on a tie.
     *
     * @return list<QueueItem>
     */
    public function findRanked(Event $event, int $limit = 100): array
    {
        return $this->rankedQueryBuilder($event)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findNextPending(Event $event): ?QueueItem
    {
        return $this->rankedQueryBuilder($event)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByStatus(Event $event, QueueItemStatus $status): ?QueueItem
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.event = :event')
            ->andWhere('q.status = :status')
            ->setParameter('event', $event->getId(), UuidType::NAME)
            ->setParameter('status', $status)
            ->orderBy('q.lockedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** An already queued or playing occurrence of the track, if any. */
    public function findUpcomingByTrack(Event $event, string $spotifyId): ?QueueItem
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.event = :event')
            ->andWhere('q.track.spotifyId = :track')
            ->andWhere('q.status IN (:statuses)')
            ->setParameter('event', $event->getId(), UuidType::NAME)
            ->setParameter('track', $spotifyId)
            ->setParameter('statuses', QueueItemStatus::upcomingValues())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findLastPlay(Event $event, string $spotifyId): ?QueueItem
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.event = :event')
            ->andWhere('q.track.spotifyId = :track')
            ->andWhere('q.playedAt IS NOT NULL')
            ->setParameter('event', $event->getId(), UuidType::NAME)
            ->setParameter('track', $spotifyId)
            ->orderBy('q.playedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countPendingForGuest(Guest $guest): int
    {
        $count = $this->createQueryBuilder('q')
            ->select('COUNT(q.id)')
            ->andWhere('q.proposedBy = :guest')
            ->andWhere('q.status = :status')
            ->setParameter('guest', $guest->getId(), UuidType::NAME)
            ->setParameter('status', QueueItemStatus::Pending)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }

    /** @return list<QueueItem> */
    public function findForGuest(Guest $guest): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.proposedBy = :guest')
            ->setParameter('guest', $guest->getId(), UuidType::NAME)
            ->orderBy('q.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<QueueItem> */
    public function findHistory(Event $event, int $limit = 30): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.event = :event')
            ->andWhere('q.status = :status')
            ->setParameter('event', $event->getId(), UuidType::NAME)
            ->setParameter('status', QueueItemStatus::Played)
            ->orderBy('q.playedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Spotify ids of the tracks already queued or playing, mapped to their queue item id.
     *
     * @return array<string, string>
     */
    public function upcomingTrackMap(Event $event): array
    {
        /** @var list<array{spotifyId: string, id: string}> $rows */
        $rows = $this->createQueryBuilder('q')
            ->select('q.track.spotifyId AS spotifyId', 'q.id AS id')
            ->andWhere('q.event = :event')
            ->andWhere('q.status IN (:statuses)')
            ->setParameter('event', $event->getId(), UuidType::NAME)
            ->setParameter('statuses', QueueItemStatus::upcomingValues())
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $map[$row['spotifyId']] = (string) $row['id'];
        }

        return $map;
    }

    /** @return list<string> Spotify ids played too recently to be proposed again */
    public function recentlyPlayedTrackIds(Event $event, int $replayDelayMinutes): array
    {
        if ($replayDelayMinutes <= 0) {
            return [];
        }

        /** @var list<array{spotifyId: string}> $rows */
        $rows = $this->createQueryBuilder('q')
            ->select('q.track.spotifyId AS spotifyId')
            ->andWhere('q.event = :event')
            ->andWhere('q.playedAt >= :since')
            ->setParameter('event', $event->getId(), UuidType::NAME)
            ->setParameter('since', new \DateTimeImmutable(\sprintf('-%d minutes', $replayDelayMinutes)))
            ->getQuery()
            ->getArrayResult();

        return array_values(array_unique(array_column($rows, 'spotifyId')));
    }

    private function rankedQueryBuilder(Event $event): \Doctrine\ORM\QueryBuilder
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.event = :event')
            ->andWhere('q.status = :status')
            ->setParameter('event', $event->getId(), UuidType::NAME)
            ->setParameter('status', QueueItemStatus::Pending)
            ->orderBy('q.pinned', 'DESC')
            ->addOrderBy('q.score', 'DESC')
            ->addOrderBy('q.createdAt', 'ASC')
            // createdAt only has a second of precision: UUIDv7 keeps same-second proposals in a stable order
            ->addOrderBy('q.id', 'ASC');
    }
}
