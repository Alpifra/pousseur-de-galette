<?php

namespace App\Spotify;

use App\Entity\Track;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Catalogue lookups for the guests. They go through an app-level token
 * (client credentials) so that guest searches never consume the host's rate limit.
 */
class SpotifyCatalog
{
    private const SEARCH_TTL = 300;
    /** Spotify rejects anything above 10 while the app is in development mode ("Invalid limit"). */
    private const MAX_LIMIT = 10;
    private const TOKEN_URL = 'https://accounts.spotify.com/api/token';

    public function __construct(
        private SpotifyClient $client,
        private HttpClientInterface $httpClient,
        private CacheInterface $cache,
        #[Autowire('%env(SPOTIFY_CLIENT_ID)%')]
        private string $clientId,
        #[Autowire('%env(SPOTIFY_CLIENT_SECRET)%')]
        private string $clientSecret,
        #[Autowire('%env(SPOTIFY_MARKET)%')]
        private string $market,
    ) {
    }

    /** @return list<Track> */
    public function search(string $query, int $limit = self::MAX_LIMIT): array
    {
        $query = trim($query);
        if ('' === $query) {
            return [];
        }

        $limit = max(1, min(self::MAX_LIMIT, $limit));

        $key = 'spotify_search_'.hash('xxh128', $this->market.'|'.$limit.'|'.mb_strtolower($query));

        /** @var list<array<string, mixed>> $raw */
        $raw = $this->cache->get($key, function (ItemInterface $item) use ($query, $limit): array {
            $item->expiresAfter(self::SEARCH_TTL);
            $payload = $this->client->request('GET', '/search', $this->appToken(), [
                'q' => $query,
                'type' => 'track',
                'market' => $this->market,
                'limit' => $limit,
            ]) ?? [];

            $tracks = \is_array($payload['tracks'] ?? null) ? $payload['tracks'] : [];

            return array_values(array_filter(
                \is_array($tracks['items'] ?? null) ? $tracks['items'] : [],
                static fn (mixed $track): bool => \is_array($track) && \is_string($track['id'] ?? null),
            ));
        });

        return array_map(static fn (array $track): Track => TrackMapper::fromApi($track), $raw);
    }

    public function track(string $spotifyId): ?Track
    {
        $payload = $this->cache->get('spotify_track_'.$spotifyId, function (ItemInterface $item) use ($spotifyId): ?array {
            $item->expiresAfter(86400);

            return $this->client->request('GET', '/tracks/'.$spotifyId, $this->appToken(), ['market' => $this->market]);
        });

        return \is_array($payload) ? TrackMapper::fromApi($payload) : null;
    }

    private function appToken(): string
    {
        if ('' === $this->clientId || '' === $this->clientSecret) {
            throw new SpotifyException('SPOTIFY_CLIENT_ID / SPOTIFY_CLIENT_SECRET are not configured.');
        }

        return $this->cache->get('spotify_app_token', function (ItemInterface $item): string {
            try {
                $response = $this->httpClient->request('POST', self::TOKEN_URL, [
                    'auth_basic' => [$this->clientId, $this->clientSecret],
                    'body' => ['grant_type' => 'client_credentials'],
                ]);
                /** @var array<string, mixed> $payload */
                $payload = $response->toArray(false);
                $status = $response->getStatusCode();
            } catch (ExceptionInterface $e) {
                throw new SpotifyException('Spotify token endpoint unreachable.', 0, $e);
            }

            if (200 !== $status) {
                throw new SpotifyException(\sprintf('Spotify refused the client credentials (%d).', $status));
            }

            $tokens = TokenSet::fromPayload($payload);
            $item->expiresAt($tokens->expiresAt->modify('-60 seconds'));

            return $tokens->accessToken;
        });
    }
}
