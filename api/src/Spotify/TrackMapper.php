<?php

namespace App\Spotify;

use App\Entity\Track;

class TrackMapper
{
    /** @param array<string, mixed> $payload a Spotify track object */
    public static function fromApi(array $payload): Track
    {
        $id = $payload['id'] ?? null;
        $name = $payload['name'] ?? null;
        if (!\is_string($id) || !\is_string($name)) {
            throw new SpotifyException('Unexpected Spotify track payload.');
        }

        $artists = [];
        if (\is_array($payload['artists'] ?? null)) {
            foreach ($payload['artists'] as $artist) {
                if (\is_array($artist) && \is_string($artist['name'] ?? null)) {
                    $artists[] = $artist['name'];
                }
            }
        }

        $album = \is_array($payload['album'] ?? null) ? $payload['album'] : [];
        $albumName = \is_string($album['name'] ?? null) ? $album['name'] : null;

        return new Track(
            spotifyId: $id,
            name: self::truncate($name, 255),
            artist: self::truncate([] === $artists ? 'Artiste inconnu' : implode(', ', $artists), 255),
            album: null !== $albumName ? self::truncate($albumName, 255) : null,
            coverUrl: self::coverUrl($album),
            durationMs: is_numeric($payload['duration_ms'] ?? null) ? (int) $payload['duration_ms'] : 0,
            explicit: true === ($payload['explicit'] ?? false),
        );
    }

    /** @param array<string, mixed> $album */
    private static function coverUrl(array $album): ?string
    {
        if (!\is_array($album['images'] ?? null)) {
            return null;
        }

        $best = null;
        $bestWidth = \PHP_INT_MAX;
        foreach ($album['images'] as $image) {
            if (!\is_array($image) || !\is_string($image['url'] ?? null)) {
                continue;
            }
            $width = is_numeric($image['width'] ?? null) ? (int) $image['width'] : 0;
            // Prefer the smallest image above 200px: the UI never shows a cover larger than that
            if (null === $best || ($width >= 200 && $width < $bestWidth)) {
                $best = $image['url'];
                $bestWidth = $width;
            }
        }

        return $best;
    }

    private static function truncate(string $value, int $max): string
    {
        return mb_strlen($value) > $max ? mb_substr($value, 0, $max) : $value;
    }
}
