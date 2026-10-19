<?php

namespace App\Tests\Double;

use App\Dto\PlaybackSnapshot;
use App\Entity\Host;
use App\Spotify\SpotifyPlayer;

/** Scripted Spotify player: the test suite never controls a real account. */
final class FakePlayer extends SpotifyPlayer
{
    public ?PlaybackSnapshot $snapshot = null;
    public ?\Throwable $failure = null;
    /** @var list<string> */
    public array $enqueued = [];
    public ?\Throwable $enqueueFailure = null;

    public function __construct()
    {
    }

    public function currentPlayback(Host $host): ?PlaybackSnapshot
    {
        if (null !== $this->failure) {
            throw $this->failure;
        }

        return $this->snapshot;
    }

    public function enqueue(Host $host, string $trackUri, ?string $deviceId = null): void
    {
        if (null !== $this->enqueueFailure) {
            throw $this->enqueueFailure;
        }

        $this->enqueued[] = $trackUri;
    }
}
