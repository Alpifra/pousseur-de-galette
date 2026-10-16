<?php

namespace App\Spotify;

use App\Entity\Host;
use App\Service\TokenCipher;
use Doctrine\ORM\EntityManagerInterface;

/** Keeps a usable Spotify access token for a host, encrypted at rest. */
class HostTokenManager
{
    private const REFRESH_MARGIN = 60;

    public function __construct(
        private SpotifyOAuth $oauth,
        private TokenCipher $cipher,
        private EntityManagerInterface $em,
    ) {
    }

    public function store(Host $host, TokenSet $tokens): void
    {
        $host->setSpotifyAccessToken($this->cipher->encrypt($tokens->accessToken));
        if (null !== $tokens->refreshToken) {
            $host->setSpotifyRefreshToken($this->cipher->encrypt($tokens->refreshToken));
        }
        $host->setSpotifyTokenExpiresAt($tokens->expiresAt);
        $this->em->flush();
    }

    public function disconnect(Host $host): void
    {
        $host->setSpotifyAccessToken(null);
        $host->setSpotifyRefreshToken(null);
        $host->setSpotifyTokenExpiresAt(null);
        $host->setSpotifyUserId(null);
        $host->setSpotifyProduct(null);
        $this->em->flush();
    }

    public function accessToken(Host $host): string
    {
        $encrypted = $host->getSpotifyAccessToken();
        $expiresAt = $host->getSpotifyTokenExpiresAt();

        if (null !== $encrypted && null !== $expiresAt && $expiresAt->getTimestamp() - time() > self::REFRESH_MARGIN) {
            return $this->cipher->decrypt($encrypted);
        }

        $refreshToken = $host->getSpotifyRefreshToken();
        if (null === $refreshToken) {
            throw new SpotifyAuthException('This host has not connected a Spotify account yet.');
        }

        try {
            $tokens = $this->oauth->refresh($this->cipher->decrypt($refreshToken));
        } catch (SpotifyAuthException $e) {
            $this->disconnect($host);

            throw $e;
        }

        $this->store($host, $tokens);

        return $tokens->accessToken;
    }
}
