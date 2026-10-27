<?php

namespace App\Spotify;

/** Spotify refused the request itself (400 / 404): retrying it as is will fail the same way. */
class RejectedRequestException extends SpotifyException
{
}
