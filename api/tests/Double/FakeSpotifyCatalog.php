<?php

namespace App\Tests\Double;

use App\Entity\Track;
use App\Spotify\SpotifyCatalog;

/** In-memory catalogue: the test suite never talks to Spotify. */
final class FakeSpotifyCatalog extends SpotifyCatalog
{
    /** @var array<string, Track> */
    public array $tracks = [];

    public function __construct()
    {
    }

    public function search(string $query, int $limit = 20): array
    {
        return array_values($this->tracks);
    }

    public function track(string $spotifyId): ?Track
    {
        return $this->tracks[$spotifyId] ?? null;
    }
}
