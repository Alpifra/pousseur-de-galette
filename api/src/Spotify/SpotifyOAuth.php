<?php

namespace App\Spotify;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Authorization Code flow with PKCE, performed by the host only. */
class SpotifyOAuth
{
    public const SCOPES = [
        'user-read-private',
        'user-read-playback-state',
        'user-modify-playback-state',
        'user-read-currently-playing',
    ];

    private const AUTHORIZE_URL = 'https://accounts.spotify.com/authorize';
    private const TOKEN_URL = 'https://accounts.spotify.com/api/token';

    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%env(SPOTIFY_CLIENT_ID)%')]
        private string $clientId,
        #[Autowire('%env(SPOTIFY_REDIRECT_URI)%')]
        private string $redirectUri,
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== $this->clientId;
    }

    public static function createCodeVerifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
    }

    public function authorizationUrl(string $codeVerifier, string $state): string
    {
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        return self::AUTHORIZE_URL.'?'.http_build_query([
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri,
            'scope' => implode(' ', self::SCOPES),
            'state' => $state,
            'code_challenge_method' => 'S256',
            'code_challenge' => $challenge,
        ]);
    }

    public function exchangeCode(string $code, string $codeVerifier): TokenSet
    {
        return $this->token([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
            'client_id' => $this->clientId,
            'code_verifier' => $codeVerifier,
        ]);
    }

    public function refresh(string $refreshToken): TokenSet
    {
        return $this->token([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $this->clientId,
        ]);
    }

    /** @param array<string, string> $body */
    private function token(array $body): TokenSet
    {
        try {
            $response = $this->httpClient->request('POST', self::TOKEN_URL, ['body' => $body]);
            $status = $response->getStatusCode();
            /** @var array<string, mixed> $payload */
            $payload = $response->toArray(false);
        } catch (ExceptionInterface $e) {
            throw new SpotifyException('Spotify token endpoint unreachable.', 0, $e);
        }

        if (200 !== $status) {
            $error = \is_string($payload['error_description'] ?? null) ? $payload['error_description'] : 'unknown error';
            if (400 === $status || 401 === $status) {
                throw new SpotifyAuthException(\sprintf('Spotify refused the authorization: %s', $error));
            }

            throw new SpotifyException(\sprintf('Spotify token request failed (%d): %s', $status, $error));
        }

        return TokenSet::fromPayload($payload);
    }
}
