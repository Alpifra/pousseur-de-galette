<?php

namespace App\Service;

use App\Entity\Event;
use App\Entity\Guest;
use App\Entity\QueueItem;
use App\Enum\QueueItemStatus;
use App\Repository\GuestRepository;
use App\Repository\QueueItemRepository;
use App\Repository\VoteRepository;

/**
 * Builds the JSON the guest app consumes. The snapshot is guest-agnostic so that
 * the very same payload can be broadcast to everyone through Mercure; personal
 * data (my votes, my quota) is merged in by personalize().
 */
class QueueSerializer
{
    private const ACTIVE_WINDOW = '-10 minutes';

    public function __construct(
        private QueueItemRepository $items,
        private GuestRepository $guests,
        private VoteRepository $votes,
        private RealtimePublisher $realtime,
    ) {
    }

    /** @return array<string, mixed> */
    public function snapshot(Event $event): array
    {
        $playing = $this->items->findOneByStatus($event, QueueItemStatus::Playing);
        $locked = $this->items->findOneByStatus($event, QueueItemStatus::Locked);
        $pending = $this->items->findRanked($event);

        $playback = $event->getPlayback();
        $elapsed = null === $playback->updatedAt ? 0 : max(0, time() - $playback->updatedAt->getTimestamp()) * 1000;
        $progressMs = $playback->isPlaying ? min($playback->durationMs, $playback->progressMs + $elapsed) : $playback->progressMs;

        $etaMs = $playback->durationMs > 0 ? max(0, $playback->durationMs - $progressMs) : 0;
        $queue = [];
        $rank = 0;
        foreach (null !== $locked ? [$locked, ...$pending] : $pending as $item) {
            $queue[] = $this->item($item, ++$rank, $etaMs);
            $etaMs += $item->getTrack()->durationMs;
        }

        return [
            'event' => [
                'id' => (string) $event->getId(),
                'name' => $event->getName(),
                'status' => $event->getStatus()->value,
                'settings' => [
                    'allowDownvotes' => $event->getSettings()->allowDownvotes,
                    'maxPendingPerGuest' => $event->getSettings()->maxPendingPerGuest,
                    'blockExplicit' => $event->getSettings()->blockExplicit,
                ],
            ],
            'guests' => $this->guests->countActive($event, new \DateTimeImmutable(self::ACTIVE_WINDOW)),
            'current' => null === $playback->trackId ? null : [
                'spotifyId' => $playback->trackId,
                'name' => $playback->trackName,
                'artist' => $playback->trackArtist,
                'coverUrl' => $playback->coverUrl,
                'durationMs' => $playback->durationMs,
                'progressMs' => $progressMs,
                'isPlaying' => $playback->isPlaying,
                'fromQueue' => $playback->fromQueue,
                'proposedBy' => null !== $playing ? $this->proposer($playing) : null,
                'queueItemId' => null !== $playing ? (string) $playing->getId() : null,
            ],
            'queue' => $queue,
            'playbackError' => $playback->error,
            'updatedAt' => (new \DateTimeImmutable())->format(\DATE_ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $snapshot
     *
     * @return array<string, mixed>
     */
    public function personalize(array $snapshot, ?Guest $guest): array
    {
        $snapshot['realtime'] = [
            'hub' => $this->realtime->hubUrl(),
            'topic' => null,
        ];

        if (null === $guest) {
            $snapshot['me'] = null;

            return $snapshot;
        }

        $event = $guest->getEvent();
        $snapshot['realtime']['topic'] = $this->realtime->topicFor($event);

        $items = [
            ...$this->items->findRanked($event),
            ...array_filter([
                $this->items->findOneByStatus($event, QueueItemStatus::Locked),
                $this->items->findOneByStatus($event, QueueItemStatus::Playing),
            ]),
        ];

        $snapshot['me'] = [
            'id' => (string) $guest->getId(),
            'pseudo' => $guest->getPseudo(),
            'pendingCount' => $this->items->countPendingForGuest($guest),
            'maxPending' => $event->getSettings()->maxPendingPerGuest,
            'votes' => $this->votes->valuesForGuest($guest, $items),
        ];

        return $snapshot;
    }

    /** @return array<string, mixed> */
    public function item(QueueItem $item, int $rank, ?int $etaMs = null): array
    {
        $track = $item->getTrack();

        return [
            'id' => (string) $item->getId(),
            'rank' => $rank,
            'status' => $item->getStatus()->value,
            'locked' => !$item->getStatus()->isVotable(),
            'pinned' => $item->isPinned(),
            'score' => $item->getScore(),
            'upvotes' => $item->getUpvotes(),
            'downvotes' => $item->getDownvotes(),
            'etaMs' => $etaMs,
            'proposedBy' => $this->proposer($item),
            'track' => [
                'spotifyId' => $track->spotifyId,
                'name' => $track->name,
                'artist' => $track->artist,
                'album' => $track->album,
                'coverUrl' => $track->coverUrl,
                'durationMs' => $track->durationMs,
                'explicit' => $track->explicit,
            ],
        ];
    }

    /** @return array{id: string, pseudo: string}|null */
    private function proposer(QueueItem $item): ?array
    {
        $guest = $item->getProposedBy();

        return null === $guest ? null : ['id' => (string) $guest->getId(), 'pseudo' => $guest->getPseudo()];
    }
}
