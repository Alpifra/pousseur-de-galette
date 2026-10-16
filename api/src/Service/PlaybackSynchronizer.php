<?php

namespace App\Service;

use App\Dto\PlaybackSnapshot;
use App\Entity\Event;
use App\Entity\QueueItem;
use App\Enum\EventStatus;
use App\Enum\QueueItemStatus;
use App\Repository\QueueItemRepository;
use App\Spotify\NoActiveDeviceException;
use App\Spotify\SpotifyAuthException;
use App\Spotify\SpotifyException;
use App\Spotify\SpotifyPlayer;
use App\Spotify\SpotifyRateLimitException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Keeps the platform queue and the Spotify player in sync.
 *
 * Spotify cannot reorder or clear its own queue, so the next track is handed over
 * at the last moment (see presentation.md § 7.3): a few seconds before the current
 * track ends, the top of the queue is locked and pushed. Everything else here is
 * about noticing what the player actually did.
 */
class PlaybackSynchronizer
{
    /** A locked track that never shows up (host cleared the Spotify queue) goes back to the queue. */
    private const LOCK_TIMEOUT = 600;

    public function __construct(
        private SpotifyPlayer $player,
        private QueueItemRepository $items,
        private QueueManager $queue,
        private RealtimePublisher $realtime,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    public function tick(Event $event): void
    {
        $host = $event->getHost();
        $playback = $event->getPlayback();

        if (!$host->isSpotifyConnected()) {
            $this->fail($event, 'spotify_disconnected');

            return;
        }

        try {
            $snapshot = $this->player->currentPlayback($host);
        } catch (SpotifyAuthException $e) {
            $this->logger->warning('Spotify token lost', ['event' => (string) $event->getId(), 'error' => $e->getMessage()]);
            $this->fail($event, 'spotify_disconnected');

            return;
        } catch (SpotifyRateLimitException) {
            return;
        } catch (NoActiveDeviceException) {
            $this->fail($event, 'no_device');

            return;
        } catch (SpotifyException $e) {
            $this->logger->error('Spotify playback poll failed', ['event' => (string) $event->getId(), 'error' => $e->getMessage()]);
            $this->fail($event, 'spotify_error');

            return;
        }

        if (null === $snapshot || null === $snapshot->track) {
            $this->fail($event, 'no_device');

            return;
        }

        if (null !== $snapshot->deviceId && null === $event->getSpotifyDeviceId()) {
            $event->setSpotifyDeviceId($snapshot->deviceId);
        }

        $trackChanged = $playback->trackId !== $snapshot->track->spotifyId;
        $fromQueue = $playback->fromQueue;

        if ($trackChanged) {
            $fromQueue = $this->handleTrackChange($event, $snapshot);
        }

        $playback->isPlaying = $snapshot->isPlaying;
        $playback->trackId = $snapshot->track->spotifyId;
        $playback->trackName = $snapshot->track->name;
        $playback->trackArtist = $snapshot->track->artist;
        $playback->coverUrl = $snapshot->track->coverUrl;
        $playback->durationMs = $snapshot->track->durationMs;
        $playback->progressMs = $snapshot->progressMs;
        $playback->deviceName = $snapshot->deviceName;
        $playback->fromQueue = $fromQueue;
        $playback->updatedAt = new \DateTimeImmutable();
        $playback->error = null;

        $this->releaseStaleLock($event);
        $pushed = $this->pushNextIfDue($event, $snapshot);

        $this->em->flush();

        if ($trackChanged || $pushed) {
            $this->queue->broadcast($event);
        } else {
            $this->realtime->publish($event, 'playback', [
                'spotifyId' => $playback->trackId,
                'progressMs' => $playback->progressMs,
                'durationMs' => $playback->durationMs,
                'isPlaying' => $playback->isPlaying,
            ]);
        }
    }

    /** @return bool whether the new track comes from the platform queue */
    private function handleTrackChange(Event $event, PlaybackSnapshot $snapshot): bool
    {
        $track = $snapshot->track;
        if (null === $track) {
            return false;
        }

        $previous = $this->items->findOneByStatus($event, QueueItemStatus::Playing);
        if (null !== $previous && $previous->getTrack()->spotifyId !== $track->spotifyId) {
            $previous->markPlayed();
        }

        $locked = $this->items->findOneByStatus($event, QueueItemStatus::Locked);
        if (null !== $locked && $locked->getTrack()->spotifyId === $track->spotifyId) {
            $locked->startPlaying();

            return true;
        }

        if (null !== $previous && $previous->getTrack()->spotifyId === $track->spotifyId) {
            return true;
        }

        // The host played something outside of the queue: keep it in the history anyway
        $outOfQueue = new QueueItem($event, $track, null);
        $outOfQueue->startPlaying();
        $this->em->persist($outOfQueue);
        $this->logger->info('Out-of-queue track playing', ['event' => (string) $event->getId(), 'track' => $track->spotifyId]);

        return false;
    }

    private function releaseStaleLock(Event $event): void
    {
        $locked = $this->items->findOneByStatus($event, QueueItemStatus::Locked);
        $lockedAt = $locked?->getLockedAt();
        if (null === $locked || null === $lockedAt) {
            return;
        }

        if (time() - $lockedAt->getTimestamp() > self::LOCK_TIMEOUT) {
            $locked->unlock();
            $this->logger->warning('Locked track never played, back to the queue', [
                'event' => (string) $event->getId(),
                'track' => $locked->getTrack()->spotifyId,
            ]);
        }
    }

    private function pushNextIfDue(Event $event, PlaybackSnapshot $snapshot): bool
    {
        if (EventStatus::Live !== $event->getStatus() || !$snapshot->isPlaying) {
            return false;
        }

        $remaining = $snapshot->remainingMs();
        if (null === $remaining || $remaining > $event->getSettings()->lockLeadMs) {
            return false;
        }

        if (null !== $this->items->findOneByStatus($event, QueueItemStatus::Locked)) {
            return false;
        }

        $next = $this->items->findNextPending($event);
        if (null === $next) {
            return false;
        }

        try {
            $this->player->enqueue($event->getHost(), $next->getTrack()->uri(), $event->getSpotifyDeviceId());
        } catch (SpotifyException $e) {
            $this->logger->error('Unable to push the next track to Spotify', [
                'event' => (string) $event->getId(),
                'track' => $next->getTrack()->spotifyId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        $next->lock();
        $this->logger->info('Next track locked and pushed', [
            'event' => (string) $event->getId(),
            'track' => $next->getTrack()->spotifyId,
            'remainingMs' => $remaining,
        ]);

        return true;
    }

    private function fail(Event $event, string $error): void
    {
        $playback = $event->getPlayback();
        $changed = $playback->error !== $error;
        $playback->error = $error;
        $playback->isPlaying = false;
        $playback->updatedAt = new \DateTimeImmutable();
        $this->em->flush();

        if ($changed) {
            $this->queue->broadcast($event);
        }
    }
}
