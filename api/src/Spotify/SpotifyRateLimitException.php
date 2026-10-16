<?php

namespace App\Spotify;

class SpotifyRateLimitException extends SpotifyException
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct(\sprintf('Spotify rate limit reached, retry in %d s.', $retryAfter));
    }
}
