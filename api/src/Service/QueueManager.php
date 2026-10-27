<?php

namespace App\Service;

use App\Entity\Event;
use App\Entity\Guest;
use App\Entity\QueueItem;
use App\Entity\Vote;
use App\Enum\EventStatus;
use App\Exception\DomainException;
use App\Repository\QueueItemRepository;
use App\Repository\VoteRepository;
use App\Spotify\SpotifyCatalog;
use App\Spotify\SpotifyException;
use Doctrine\ORM\EntityManagerInterface;

class QueueManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private QueueItemRepository $items,
        private VoteRepository $votes,
        private SpotifyCatalog $catalog,
        private QueueSerializer $serializer,
        private RealtimePublisher $realtime,
    ) {
    }

    /**
     * Adds a track to the queue. Proposing a track that is already queued counts as an upvote.
     *
     * @return array{item: QueueItem, alreadyQueued: bool, snapshot: array<string, mixed>}
     */
    public function propose(Guest $guest, string $spotifyId): array
    {
        $event = $guest->getEvent();
        $this->assertContributionsAllowed($event);
        $settings = $event->getSettings();

        $existing = $this->items->findUpcomingByTrack($event, $spotifyId);
        if (null !== $existing) {
            if ($existing->getStatus()->isVotable() && !$this->isAuthor($guest, $existing)) {
                $this->castVote($guest, $existing, 1);
            }

            return ['item' => $existing, 'alreadyQueued' => true, 'snapshot' => $this->broadcast($event)];
        }

        if ($settings->replayDelayMinutes > 0) {
            $lastPlay = $this->items->findLastPlay($event, $spotifyId);
            $playedAt = $lastPlay?->getPlayedAt();
            if (null !== $playedAt && $playedAt > new \DateTimeImmutable(\sprintf('-%d minutes', $settings->replayDelayMinutes))) {
                throw DomainException::replayDelay($settings->replayDelayMinutes);
            }
        }

        if ($this->items->countPendingForGuest($guest) >= $settings->maxPendingPerGuest) {
            throw DomainException::quotaExceeded($settings->maxPendingPerGuest);
        }

        try {
            $track = $this->catalog->track($spotifyId);
        } catch (SpotifyException $e) {
            throw DomainException::spotifyUnreachable($e);
        }

        if (null === $track) {
            throw DomainException::trackNotFound();
        }

        if ($track->explicit && $settings->blockExplicit) {
            throw DomainException::explicitBlocked();
        }

        if ($track->durationMs > $settings->maxTrackDurationMs) {
            throw DomainException::trackTooLong($settings->maxTrackDurationMs);
        }

        $item = new QueueItem($event, $track, $guest);
        $this->em->persist($item);
        // The proposal itself counts as an upvote from its author
        $vote = new Vote($item, $guest, 1);
        $this->em->persist($vote);
        $item->getVotes()->add($vote);
        $item->recountVotes();
        $this->em->flush();

        return ['item' => $item, 'alreadyQueued' => false, 'snapshot' => $this->broadcast($event)];
    }

    /** @return array{item: QueueItem, snapshot: array<string, mixed>} */
    public function vote(Guest $guest, QueueItem $item, int $value): array
    {
        $event = $guest->getEvent();
        if (!$item->getEvent()->getId()->equals($event->getId())) {
            throw DomainException::notFound('Titre');
        }

        $this->assertContributionsAllowed($event);
        $this->castVote($guest, $item, $value);
        $this->em->flush();

        return ['item' => $item, 'snapshot' => $this->broadcast($event)];
    }

    /**
     * The author takes back their own proposal, which frees a slot in their quota.
     *
     * @return array<string, mixed> the snapshot that was published
     */
    public function withdraw(Guest $guest, QueueItem $item): array
    {
        $event = $guest->getEvent();
        if (!$item->getEvent()->getId()->equals($event->getId()) || !$this->isAuthor($guest, $item)) {
            throw DomainException::notFound('Titre');
        }

        $this->assertContributionsAllowed($event);
        if (!$item->getStatus()->isVotable()) {
            throw DomainException::itemLocked();
        }

        $item->remove();
        $this->em->flush();

        return $this->broadcast($event);
    }

    public function remove(QueueItem $item): void
    {
        $item->remove();
        $this->em->flush();
        $this->broadcast($item->getEvent());
    }

    public function pin(QueueItem $item, bool $pinned): void
    {
        $item->setPinned($pinned);
        $this->em->flush();
        $this->broadcast($item->getEvent());
    }

    /**
     * Publishes the current state of the queue to every guest.
     *
     * @return array<string, mixed> the snapshot that was published
     */
    public function broadcast(Event $event): array
    {
        $snapshot = $this->serializer->snapshot($event);
        $this->realtime->publish($event, 'queue', $snapshot);

        return $snapshot;
    }

    private function castVote(Guest $guest, QueueItem $item, int $value): void
    {
        $value = max(-1, min(1, $value));

        if (!$item->getStatus()->isVotable()) {
            throw DomainException::itemLocked();
        }

        // Proposing already is the author's upvote: they withdraw the track instead of voting on it
        if ($this->isAuthor($guest, $item)) {
            throw DomainException::ownProposal();
        }

        if ($value < 0 && !$item->getEvent()->getSettings()->allowDownvotes) {
            throw DomainException::downvotesDisabled();
        }

        $vote = $this->votes->findOneFor($item, $guest);
        if (0 === $value) {
            if (null !== $vote) {
                $item->getVotes()->removeElement($vote);
                $this->em->remove($vote);
            }
        } elseif (null !== $vote) {
            $vote->setValue($value);
        } else {
            $vote = new Vote($item, $guest, $value);
            $this->em->persist($vote);
            $item->getVotes()->add($vote);
        }

        $item->recountVotes();

        $threshold = $item->getEvent()->getSettings()->rejectionThreshold;
        if (!$item->isPinned() && $item->getScore() <= $threshold) {
            $item->reject();
        }
    }

    private function isAuthor(Guest $guest, QueueItem $item): bool
    {
        return $item->getProposedBy()?->getId()->equals($guest->getId()) ?? false;
    }

    private function assertContributionsAllowed(Event $event): void
    {
        if (EventStatus::Ended === $event->getStatus()) {
            throw DomainException::eventEnded();
        }

        if (!$event->getStatus()->acceptsContributions()) {
            throw DomainException::eventNotLive();
        }
    }
}
