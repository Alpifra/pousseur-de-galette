<?php

namespace App\Tests\Integration;

use App\Dto\PlaybackSnapshot;
use App\Entity\Event;
use App\Entity\QueueItem;
use App\Entity\Track;
use App\Enum\QueueItemStatus;
use App\Repository\QueueItemRepository;
use App\Service\PlaybackSynchronizer;
use App\Spotify\NoActiveDeviceException;
use App\Spotify\PremiumRequiredException;
use App\Spotify\RejectedRequestException;
use App\Spotify\SpotifyPlayer;
use App\Tests\Double\FakePlayer;

class PlaybackSynchronizerTest extends DomainTestCase
{
    private FakePlayer $player;
    private PlaybackSynchronizer $sync;
    private QueueItemRepository $items;

    protected function setUp(): void
    {
        parent::setUp();

        $this->player = new FakePlayer();
        self::getContainer()->set(SpotifyPlayer::class, $this->player);

        /** @var PlaybackSynchronizer $sync */
        $sync = self::getContainer()->get(PlaybackSynchronizer::class);
        $this->sync = $sync;
        /** @var QueueItemRepository $items */
        $items = self::getContainer()->get(QueueItemRepository::class);
        $this->items = $items;
    }

    public function testTheCurrentTrackIsMirroredOnTheEvent(): void
    {
        $event = $this->connectedEvent();
        $this->player->snapshot = $this->playing($this->track('live1', 'Voyage voyage'), 30_000);

        $this->sync->tick($event);

        $playback = $event->getPlayback();
        self::assertSame('live1', $playback->trackId);
        self::assertSame('Voyage voyage', $playback->trackName);
        self::assertSame('Enceinte du salon', $playback->deviceName);
        self::assertTrue($playback->isPlaying);
        self::assertNull($playback->error);
        self::assertSame('device-1', $event->getSpotifyDeviceId());
    }

    public function testATrackPlayedOutsideOfTheQueueIsKeptInTheHistory(): void
    {
        $event = $this->connectedEvent();
        $this->player->snapshot = $this->playing($this->track('host1', 'Choix de l\'hôte'), 1_000);

        $this->sync->tick($event);

        $playing = $this->items->findOneByStatus($event, QueueItemStatus::Playing);
        self::assertNotNull($playing);
        self::assertSame('host1', $playing->getTrack()->spotifyId);
        self::assertNull($playing->getProposedBy());
        self::assertFalse($event->getPlayback()->fromQueue);
    }

    public function testTheTopOfTheQueueIsPushedAndLockedBeforeTheEndOfTheTrack(): void
    {
        $event = $this->connectedEvent();
        $next = $this->pending($event, 'next1');
        $this->player->snapshot = $this->playing($this->track('live1'), 195_000);

        $this->sync->tick($event);

        self::assertSame(['spotify:track:next1'], $this->player->enqueued);
        self::assertSame(QueueItemStatus::Locked, $next->getStatus());
        self::assertNotNull($next->getLockedAt());
    }

    public function testNothingIsPushedWhileTheCurrentTrackHasTimeLeft(): void
    {
        $event = $this->connectedEvent();
        $next = $this->pending($event, 'next1');
        $this->player->snapshot = $this->playing($this->track('live1'), 10_000);

        $this->sync->tick($event);

        self::assertSame([], $this->player->enqueued);
        self::assertSame(QueueItemStatus::Pending, $next->getStatus());
    }

    public function testOnlyOneTrackIsHandedOverAtATime(): void
    {
        $event = $this->connectedEvent();
        $this->pending($event, 'next1');
        $this->pending($event, 'next2');
        $this->player->snapshot = $this->playing($this->track('live1'), 195_000);

        $this->sync->tick($event);
        $this->sync->tick($event);

        self::assertSame(['spotify:track:next1'], $this->player->enqueued);
    }

    public function testATrackRefusedBySpotifyIsSkipped(): void
    {
        $event = $this->connectedEvent();
        $refused = $this->pending($event, 'gone1');
        $this->pending($event, 'next2');
        $this->player->snapshot = $this->playing($this->track('live1'), 195_000);
        $this->player->enqueueFailure = new RejectedRequestException('Spotify API error 404 on POST /me/player/queue.');

        $this->sync->tick($event);
        $this->player->enqueueFailure = null;
        $this->sync->tick($event);

        self::assertSame(QueueItemStatus::Rejected, $refused->getStatus());
        self::assertSame(['spotify:track:next2'], $this->player->enqueued);
    }

    public function testTheTrackIsPushedToTheDeviceCurrentlyPlaying(): void
    {
        $event = $this->connectedEvent();
        $event->setSpotifyDeviceId('previous-speaker');
        $this->pending($event, 'next1');
        $this->player->snapshot = $this->playing($this->track('live1'), 195_000);

        $this->sync->tick($event);

        self::assertSame(['device-1'], $this->player->devices);
    }

    public function testTheLockedTrackBecomesTheCurrentOne(): void
    {
        $event = $this->connectedEvent();
        $next = $this->pending($event, 'next1', 'Dernière danse');
        $this->player->snapshot = $this->playing($this->track('live1'), 195_000);
        $this->sync->tick($event);

        $this->player->snapshot = $this->playing($this->track('next1', 'Dernière danse'), 0);
        $this->sync->tick($event);

        self::assertSame(QueueItemStatus::Playing, $next->getStatus());
        self::assertTrue($event->getPlayback()->fromQueue);
        self::assertSame([], $this->items->findRanked($event));
    }

    public function testAnAccountWithoutPremiumIsReportedAsAPlaybackError(): void
    {
        $event = $this->connectedEvent();
        $this->player->failure = new PremiumRequiredException('not premium');

        $this->sync->tick($event);

        self::assertSame('premium_required', $event->getPlayback()->error);
        self::assertFalse($event->getPlayback()->isPlaying);
    }

    public function testAnAccountWithoutAnActiveDeviceIsReportedAsAPlaybackError(): void
    {
        $event = $this->connectedEvent();
        $this->player->failure = new NoActiveDeviceException('no device');

        $this->sync->tick($event);

        self::assertSame('no_device', $event->getPlayback()->error);
    }

    public function testAnEventWithoutASpotifyAccountIsReportedAsDisconnected(): void
    {
        $event = $this->createEvent();

        $this->sync->tick($event);

        self::assertSame('spotify_disconnected', $event->getPlayback()->error);
    }

    private function connectedEvent(): Event
    {
        $event = $this->createEvent();
        $host = $event->getHost();
        $host->setSpotifyAccessToken('encrypted-access');
        $host->setSpotifyRefreshToken('encrypted-refresh');
        $host->setSpotifyTokenExpiresAt(new \DateTimeImmutable('+1 hour'));
        $this->em->flush();

        return $event;
    }

    private function pending(Event $event, string $spotifyId, string $name = 'Titre'): QueueItem
    {
        $item = new QueueItem($event, $this->track($spotifyId, $name), null);
        $this->em->persist($item);
        $this->em->flush();

        return $item;
    }

    private function playing(Track $track, int $progressMs): PlaybackSnapshot
    {
        return new PlaybackSnapshot(
            isPlaying: true,
            track: $track,
            progressMs: $progressMs,
            deviceId: 'device-1',
            deviceName: 'Enceinte du salon',
        );
    }
}
