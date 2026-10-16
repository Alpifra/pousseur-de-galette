<?php

namespace App\Tests\Unit;

use App\Spotify\SpotifyException;
use App\Spotify\TrackMapper;
use PHPUnit\Framework\TestCase;

class TrackMapperTest extends TestCase
{
    public function testMapsASpotifyPayload(): void
    {
        $track = TrackMapper::fromApi([
            'id' => '4uLU6hMCjMI75M1A2tKUQC',
            'name' => 'Never Gonna Give You Up',
            'duration_ms' => 213_000,
            'explicit' => false,
            'artists' => [['name' => 'Rick Astley'], ['name' => 'Someone']],
            'album' => [
                'name' => 'Whenever You Need Somebody',
                'images' => [
                    ['url' => 'https://cdn.test/640.jpg', 'width' => 640],
                    ['url' => 'https://cdn.test/300.jpg', 'width' => 300],
                    ['url' => 'https://cdn.test/64.jpg', 'width' => 64],
                ],
            ],
        ]);

        self::assertSame('Rick Astley, Someone', $track->artist);
        self::assertSame('Whenever You Need Somebody', $track->album);
        self::assertSame(213_000, $track->durationMs);
        self::assertSame('spotify:track:4uLU6hMCjMI75M1A2tKUQC', $track->uri());
        // The smallest cover above 200px is enough for the UI
        self::assertSame('https://cdn.test/300.jpg', $track->coverUrl);
    }

    public function testFallsBackWhenMetadataIsMissing(): void
    {
        $track = TrackMapper::fromApi(['id' => 'x', 'name' => 'Sans artiste']);

        self::assertSame('Artiste inconnu', $track->artist);
        self::assertNull($track->album);
        self::assertNull($track->coverUrl);
        self::assertSame(0, $track->durationMs);
    }

    public function testRejectsAnUnusablePayload(): void
    {
        $this->expectException(SpotifyException::class);

        TrackMapper::fromApi(['name' => 'Sans identifiant']);
    }
}
