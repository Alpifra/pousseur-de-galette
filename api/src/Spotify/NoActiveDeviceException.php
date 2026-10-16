<?php

namespace App\Spotify;

/** Spotify answers 404 "NO_ACTIVE_DEVICE" when nothing is playing on the account. */
class NoActiveDeviceException extends SpotifyException
{
}
