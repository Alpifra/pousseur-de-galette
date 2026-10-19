<?php

namespace App\Spotify;

/** The connected account cannot be controlled remotely: Spotify requires Premium for the player endpoints. */
class PremiumRequiredException extends SpotifyException
{
}
