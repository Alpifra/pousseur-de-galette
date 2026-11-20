<?php

namespace App\Controller;

use App\Entity\Event;
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

    /** json_login authenticates first and, without a success handler, lets the request reach this action. */
    #[Route('/login', name: 'api_host_login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        return $this->me();
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
            'events' => array_map(fn (Event $event) => [
                'id' => (string) $event->getId(),
                'name' => $event->getName(),
                'status' => $event->getStatus()->value,
                'joinCode' => $event->getJoinCode(),
                'joinUrl' => $this->links->url($event),
                'scheduledFor' => $event->getScheduledFor()?->format('Y-m-d'),
                'createdAt' => $event->getCreatedAt()->format(\DATE_ATOM),
            ], $this->newestFirst($host->getEvents()->toArray())),
        ]);
    }

    /**
     * @param array<int, Event> $events
     *
     * @return list<Event>
     */
    private function newestFirst(array $events): array
    {
        usort($events, static fn (Event $a, Event $b) => $b->getCreatedAt() <=> $a->getCreatedAt());

        return $events;
    }
}
