<?php

namespace App\Controller;

use App\Entity\Host;
use App\Exception\DomainException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/host')]
class HostController
{
    public function __construct(private Security $security)
    {
    }

    /** Handled by the json_login firewall; the body is never reached. */
    #[Route('/login', name: 'api_host_login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        throw DomainException::invalidSession();
    }

    /** Handled by the logout listener. */
    #[Route('/logout', name: 'api_host_logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        throw new \LogicException('Intercepted by the logout listener.');
    }

    #[Route('/me', name: 'api_host_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $host = $this->security->getUser();
        if (!$host instanceof Host) {
            throw DomainException::invalidSession();
        }

        return new JsonResponse([
            'id' => (string) $host->getId(),
            'email' => $host->getEmail(),
            'spotify' => [
                'connected' => $host->isSpotifyConnected(),
                'premium' => $host->hasPremium(),
            ],
            'events' => array_map(static fn ($event) => [
                'id' => (string) $event->getId(),
                'name' => $event->getName(),
                'status' => $event->getStatus()->value,
                'joinCode' => $event->getJoinCode(),
            ], $host->getEvents()->toArray()),
        ]);
    }
}
