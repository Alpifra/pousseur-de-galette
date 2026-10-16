<?php

namespace App\Spotify;

use App\Dto\PlaybackSnapshot;
use App\Dto\SpotifyDevice;
use App\Entity\Host;

/** Playback control on the host's Spotify account. */
class SpotifyPlayer
{
    public function __construct(
        private SpotifyClient $client,
        private HostTokenManager $tokens,
    ) {
    }

    /** @return array{id: string, displayName: ?string, product: ?string} */
    public function profile(Host $host): array
    {
        $payload = $this->client->request('GET', '/me', $this->tokens->accessToken($host)) ?? [];
        $id = $payload['id'] ?? null;
        if (!\is_string($id)) {
            throw new SpotifyException('Unable to read the Spotify profile.');
        }

        return [
            'id' => $id,
            'displayName' => \is_string($payload['display_name'] ?? null) ? $payload['display_name'] : null,
            'product' => \is_string($payload['product'] ?? null) ? $payload['product'] : null,
        ];
    }

    /** Null when the account has no player session at all. */
    public function currentPlayback(Host $host): ?PlaybackSnapshot
    {
        $payload = $this->client->request('GET', '/me/player', $this->tokens->accessToken($host), ['additional_types' => 'track']);
        if (null === $payload) {
            return null;
        }

        $item = \is_array($payload['item'] ?? null) ? $payload['item'] : null;
        $device = \is_array($payload['device'] ?? null) ? $payload['device'] : [];

        return new PlaybackSnapshot(
            isPlaying: true === ($payload['is_playing'] ?? false),
            track: null !== $item && 'track' === ($item['type'] ?? 'track') ? TrackMapper::fromApi($item) : null,
            progressMs: is_numeric($payload['progress_ms'] ?? null) ? (int) $payload['progress_ms'] : 0,
            deviceId: \is_string($device['id'] ?? null) ? $device['id'] : null,
            deviceName: \is_string($device['name'] ?? null) ? $device['name'] : null,
        );
    }

    /** @return list<SpotifyDevice> */
    public function devices(Host $host): array
    {
        $payload = $this->client->request('GET', '/me/player/devices', $this->tokens->accessToken($host)) ?? [];
        $devices = [];
        foreach (\is_array($payload['devices'] ?? null) ? $payload['devices'] : [] as $device) {
            if (!\is_array($device) || !\is_string($device['id'] ?? null)) {
                continue;
            }
            $devices[] = new SpotifyDevice(
                id: $device['id'],
                name: \is_string($device['name'] ?? null) ? $device['name'] : 'Appareil',
                type: \is_string($device['type'] ?? null) ? $device['type'] : 'Unknown',
                isActive: true === ($device['is_active'] ?? false),
                isRestricted: true === ($device['is_restricted'] ?? false),
            );
        }

        return $devices;
    }

    public function enqueue(Host $host, string $trackUri, ?string $deviceId = null): void
    {
        $query = ['uri' => $trackUri];
        if (null !== $deviceId) {
            $query['device_id'] = $deviceId;
        }

        $this->client->request('POST', '/me/player/queue', $this->tokens->accessToken($host), $query);
    }

    public function next(Host $host, ?string $deviceId = null): void
    {
        $query = null !== $deviceId ? ['device_id' => $deviceId] : [];
        $this->client->request('POST', '/me/player/next', $this->tokens->accessToken($host), $query);
    }

    public function resume(Host $host, ?string $deviceId = null): void
    {
        $query = null !== $deviceId ? ['device_id' => $deviceId] : [];
        $this->client->request('PUT', '/me/player/play', $this->tokens->accessToken($host), $query);
    }

    public function pause(Host $host, ?string $deviceId = null): void
    {
        $query = null !== $deviceId ? ['device_id' => $deviceId] : [];
        $this->client->request('PUT', '/me/player/pause', $this->tokens->accessToken($host), $query);
    }
}
