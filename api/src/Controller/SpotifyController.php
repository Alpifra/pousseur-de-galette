<?php

namespace App\Controller;

use App\Entity\Host;
use App\Exception\DomainException;
use App\Spotify\HostTokenManager;
use App\Spotify\SpotifyAuthException;
use App\Spotify\SpotifyException;
use App\Spotify\SpotifyOAuth;
use App\Spotify\SpotifyPlayer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/spotify')]
class SpotifyController
{
    private const STATE_KEY = 'spotify_oauth_state';
    private const VERIFIER_KEY = 'spotify_oauth_verifier';

    public function __construct(
        private SpotifyOAuth $oauth,
        private SpotifyPlayer $player,
        private HostTokenManager $tokens,
        private Security $security,
        private EntityManagerInterface $em,
    ) {
    }

    #[Route('/connect', name: 'api_spotify_connect', methods: ['GET'])]
    public function connect(Request $request): RedirectResponse
    {
        $this->host();

        if (!$this->oauth->isConfigured()) {
            throw DomainException::spotifyUnavailable('SPOTIFY_CLIENT_ID is not configured.');
        }

        $verifier = SpotifyOAuth::createCodeVerifier();
        $state = bin2hex(random_bytes(16));
        $session = $request->getSession();
        $session->set(self::VERIFIER_KEY, $verifier);
        $session->set(self::STATE_KEY, $state);

        return new RedirectResponse($this->oauth->authorizationUrl($verifier, $state));
    }

    #[Route('/callback', name: 'api_spotify_callback', methods: ['GET'])]
    public function callback(Request $request): RedirectResponse
    {
        $host = $this->host();
        $session = $request->getSession();
        $expectedState = $session->remove(self::STATE_KEY);
        $verifier = $session->remove(self::VERIFIER_KEY);

        $error = $request->query->get('error');
        if (\is_string($error) && '' !== $error) {
            return new RedirectResponse('/host?spotify=denied');
        }

        $code = $request->query->get('code');
        $state = $request->query->get('state');
        if (!\is_string($code) || !\is_string($verifier) || !\is_string($expectedState) || !hash_equals($expectedState, (string) $state)) {
            return new RedirectResponse('/host?spotify=invalid_state');
        }

        try {
            $this->tokens->store($host, $this->oauth->exchangeCode($code, $verifier));
            $profile = $this->player->profile($host);
        } catch (SpotifyException) {
            return new RedirectResponse('/host?spotify=failed');
        }

        $host->setSpotifyUserId($profile['id']);
        $host->setSpotifyProduct($profile['product']);
        $this->em->flush();

        return new RedirectResponse($host->hasPremium() ? '/host?spotify=connected' : '/host?spotify=not_premium');
    }

    #[Route('/status', name: 'api_spotify_status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        $host = $this->host();

        return new JsonResponse([
            'configured' => $this->oauth->isConfigured(),
            'connected' => $host->isSpotifyConnected(),
            'premium' => $host->hasPremium(),
            'spotifyUserId' => $host->getSpotifyUserId(),
        ]);
    }

    #[Route('/disconnect', name: 'api_spotify_disconnect', methods: ['POST'])]
    public function disconnect(): JsonResponse
    {
        $this->tokens->disconnect($this->host());

        return new JsonResponse(null, 204);
    }

    #[Route('/devices', name: 'api_spotify_devices', methods: ['GET'])]
    public function devices(): JsonResponse
    {
        $host = $this->host();

        try {
            $devices = $this->player->devices($host);
        } catch (SpotifyAuthException $e) {
            throw DomainException::spotifyUnavailable($e->getMessage());
        }

        return new JsonResponse(['devices' => array_map(static fn ($device) => [
            'id' => $device->id,
            'name' => $device->name,
            'type' => $device->type,
            'isActive' => $device->isActive,
            'isRestricted' => $device->isRestricted,
        ], $devices)]);
    }

    private function host(): Host
    {
        $host = $this->security->getUser();
        if (!$host instanceof Host) {
            throw DomainException::invalidSession();
        }

        return $host;
    }
}
