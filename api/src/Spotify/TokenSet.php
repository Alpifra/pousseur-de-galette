<?php

namespace App\Spotify;

readonly class TokenSet
{
    public function __construct(
        public string $accessToken,
        public ?string $refreshToken,
        public \DateTimeImmutable $expiresAt,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        $accessToken = $payload['access_token'] ?? null;
        if (!\is_string($accessToken) || '' === $accessToken) {
            throw new SpotifyException('Spotify returned no access token.');
        }

        $refreshToken = $payload['refresh_token'] ?? null;
        $expiresIn = isset($payload['expires_in']) && is_numeric($payload['expires_in']) ? (int) $payload['expires_in'] : 3600;

        return new self(
            $accessToken,
            \is_string($refreshToken) && '' !== $refreshToken ? $refreshToken : null,
            new \DateTimeImmutable(\sprintf('+%d seconds', $expiresIn)),
        );
    }
}
