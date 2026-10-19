<?php

namespace App\Controller;

use App\Entity\Host;
use App\Exception\DomainException;
use App\Service\JoinLink;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/host')]
class HostController
{
    public function __construct(
        private Security $security,
        private JoinLink $links,
    ) {
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
                'premium' => null === $host->getSpotifyProduct() ? null : $host->isPremiumConfirmed(),
            ],
            'events' => array_map(fn ($event) => [
                'id' => (string) $event->getId(),
                'name' => $event->getName(),
                'status' => $event->getStatus()->value,
                'joinCode' => $event->getJoinCode(),
                'joinUrl' => $this->links->url($event),
            ], $host->getEvents()->toArray()),
        ]);
    }
}
