<?php

namespace App\Spotify;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Thin transport layer over the Spotify Web API: URLs, errors and rate limiting. */
class SpotifyClient
{
    private const BASE_URL = 'https://api.spotify.com/v1';
    private const MAX_RETRY_DELAY = 2;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, scalar|array<scalar>> $query
     * @param array<string, mixed>|null           $json
     *
     * @return array<string, mixed>|null null on an empty body (204)
     */
    public function request(string $method, string $path, string $accessToken, array $query = [], ?array $json = null, bool $retry = true): ?array
    {
        $options = ['auth_bearer' => $accessToken];
        if ([] !== $query) {
            $options['query'] = $query;
        }
        if (null !== $json) {
            $options['json'] = $json;
        }

        try {
            $response = $this->httpClient->request($method, self::BASE_URL.$path, $options);
            $status = $response->getStatusCode();
            $content = $response->getContent(false);
            $retryAfter = $response->getHeaders(false)['retry-after'][0] ?? null;
        } catch (ExceptionInterface $e) {
            throw new SpotifyException(\sprintf('Spotify API unreachable (%s %s).', $method, $path), 0, $e);
        }

        if ($status >= 200 && $status < 300) {
            if ('' === trim($content)) {
                return null;
            }

            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

            return $decoded;
        }

        if (429 === $status) {
            $delay = max(1, (int) ($retryAfter ?? 1));
            if ($retry && 'GET' === $method && $delay <= self::MAX_RETRY_DELAY) {
                $this->logger->warning('Spotify rate limited, retrying', ['path' => $path, 'delay' => $delay]);
                sleep($delay);

                return $this->request($method, $path, $accessToken, $query, $json, false);
            }

            throw new SpotifyRateLimitException($delay);
        }

        if (401 === $status) {
            throw new SpotifyAuthException(\sprintf('Spotify rejected the access token (%s %s).', $method, $path));
        }

        if (403 === $status && str_contains($content, 'PREMIUM_REQUIRED')) {
            throw new PremiumRequiredException('The connected Spotify account is not Premium.');
        }

        if (404 === $status && str_contains($content, 'NO_ACTIVE_DEVICE')) {
            throw new NoActiveDeviceException('No active Spotify device on the host account.');
        }

        $this->logger->error('Spotify API error', ['method' => $method, 'path' => $path, 'status' => $status, 'body' => $content]);

        throw new SpotifyException(\sprintf('Spotify API error %d on %s %s.', $status, $method, $path));
    }
}
