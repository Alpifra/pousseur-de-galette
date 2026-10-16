<?php

namespace App\Controller;

use App\Exception\DomainException;
use App\Repository\EventRepository;
use App\Service\EventManager;
use App\Service\GuestSession;
use App\Service\QueueSerializer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SessionController
{
    public function __construct(
        private EventRepository $events,
        private EventManager $eventManager,
        private GuestSession $session,
        private QueueSerializer $serializer,
    ) {
    }

    /** Public lookup from the join screen: enough to confirm the code before asking for a pseudo. */
    #[Route('/api/events/{code}', name: 'api_event_show', requirements: ['code' => '[A-Za-z0-9]{4,12}'], methods: ['GET'])]
    public function show(string $code): JsonResponse
    {
        $event = $this->events->findOneByJoinCode($code);
        if (null === $event) {
            throw DomainException::notFound('Soirée');
        }

        return new JsonResponse([
            'id' => (string) $event->getId(),
            'name' => $event->getName(),
            'status' => $event->getStatus()->value,
            'joinCode' => $event->getJoinCode(),
        ]);
    }

    #[Route('/api/events/{code}/join', name: 'api_event_join', requirements: ['code' => '[A-Za-z0-9]{4,12}'], methods: ['POST'])]
    public function join(string $code, Request $request): JsonResponse
    {
        $event = $this->events->findOneByJoinCode($code);
        if (null === $event) {
            throw DomainException::notFound('Soirée');
        }

        /** @var array<string, mixed> $body */
        $body = json_decode($request->getContent() ?: '{}', true, 512, \JSON_THROW_ON_ERROR);
        $pseudo = $body['pseudo'] ?? null;
        if (!\is_string($pseudo)) {
            throw new DomainException('invalid_pseudo', 'Choisis un pseudo.');
        }

        $guest = $this->eventManager->join($event, $pseudo);
        $response = new JsonResponse($this->serializer->personalize($this->serializer->snapshot($event), $guest), Response::HTTP_CREATED);
        $response->headers->setCookie($this->session->cookie($guest));

        return $response;
    }

    /** Restores the app state after a reload: the cookie alone says who the guest is and where. */
    #[Route('/api/session', name: 'api_session', methods: ['GET'])]
    public function session(Request $request): JsonResponse
    {
        $guest = $this->session->resolve($request);
        if (null === $guest) {
            return new JsonResponse(['guest' => null]);
        }

        return new JsonResponse([
            'guest' => [
                'id' => (string) $guest->getId(),
                'pseudo' => $guest->getPseudo(),
                'banned' => $guest->isBanned(),
            ],
            'event' => [
                'id' => (string) $guest->getEvent()->getId(),
                'name' => $guest->getEvent()->getName(),
                'status' => $guest->getEvent()->getStatus()->value,
                'joinCode' => $guest->getEvent()->getJoinCode(),
            ],
        ]);
    }

    #[Route('/api/session', name: 'api_session_leave', methods: ['DELETE'])]
    public function leave(): JsonResponse
    {
        $response = new JsonResponse(null, Response::HTTP_NO_CONTENT);
        $response->headers->setCookie($this->session->clearCookie());

        return $response;
    }
}
