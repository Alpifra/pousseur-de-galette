<?php

namespace App\Controller;

use App\Entity\QueueItem;
use App\Enum\QueueItemStatus;
use App\Exception\DomainException;
use App\Repository\QueueItemRepository;
use App\Service\GuestSession;
use App\Service\QueueManager;
use App\Service\QueueSerializer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

class QueueController
{
    public function __construct(
        private QueueManager $queue,
        private QueueSerializer $serializer,
        private QueueItemRepository $items,
        private GuestSession $session,
    ) {
    }

    #[Route('/api/queue', name: 'api_queue', methods: ['GET'])]
    public function queue(Request $request): JsonResponse
    {
        $guest = $this->session->require($request);

        return new JsonResponse($this->serializer->personalize($this->serializer->snapshot($guest->getEvent()), $guest));
    }

    #[Route('/api/proposals', name: 'api_propose', methods: ['POST'])]
    public function propose(Request $request): JsonResponse
    {
        $guest = $this->session->require($request);

        /** @var array<string, mixed> $body */
        $body = json_decode($request->getContent() ?: '{}', true, 512, \JSON_THROW_ON_ERROR);
        $spotifyId = $body['spotifyId'] ?? null;
        if (!\is_string($spotifyId) || 1 !== preg_match('/^[A-Za-z0-9]{10,40}$/', $spotifyId)) {
            throw new DomainException('invalid_track', 'Titre invalide.', 400);
        }

        $result = $this->queue->propose($guest, $spotifyId);
        $snapshot = $this->serializer->personalize($result['snapshot'], $guest);

        return new JsonResponse([
            'alreadyQueued' => $result['alreadyQueued'],
            'item' => $this->findInSnapshot($snapshot, $result['item']),
            'snapshot' => $snapshot,
        ]);
    }

    #[Route('/api/items/{id}/vote', name: 'api_vote', methods: ['PUT'])]
    public function vote(string $id, Request $request): JsonResponse
    {
        $guest = $this->session->require($request);
        $item = $this->item($id);

        /** @var array<string, mixed> $body */
        $body = json_decode($request->getContent() ?: '{}', true, 512, \JSON_THROW_ON_ERROR);
        $value = $body['value'] ?? null;
        if (!\is_int($value) || !\in_array($value, [-1, 0, 1], true)) {
            throw new DomainException('invalid_vote', 'Vote invalide.', 400);
        }

        $result = $this->queue->vote($guest, $item, $value);
        $snapshot = $this->serializer->personalize($result['snapshot'], $guest);

        return new JsonResponse([
            'item' => $this->findInSnapshot($snapshot, $result['item']),
            'snapshot' => $snapshot,
        ]);
    }

    /** "Mes galettes": what this guest proposed, grouped by outcome. */
    #[Route('/api/my-proposals', name: 'api_my_proposals', methods: ['GET'])]
    public function myProposals(Request $request): JsonResponse
    {
        $guest = $this->session->require($request);
        $snapshot = $this->serializer->snapshot($guest->getEvent());

        $groups = ['pending' => [], 'played' => [], 'removed' => []];
        foreach ($this->items->findForGuest($guest) as $item) {
            $view = $this->findInSnapshot($snapshot, $item) ?? $this->serializer->item($item, 0);
            $group = match ($item->getStatus()) {
                QueueItemStatus::Pending, QueueItemStatus::Locked, QueueItemStatus::Playing => 'pending',
                QueueItemStatus::Played => 'played',
                QueueItemStatus::Removed, QueueItemStatus::Rejected => 'removed',
            };
            $groups[$group][] = $view;
        }

        $nextRank = null;
        foreach ($groups['pending'] as $view) {
            $rank = \is_int($view['rank'] ?? null) ? $view['rank'] : 0;
            if ($rank > 0 && (null === $nextRank || $rank < $nextRank)) {
                $nextRank = $rank;
            }
        }

        return new JsonResponse([
            'pseudo' => $guest->getPseudo(),
            'pending' => $groups['pending'],
            'played' => $groups['played'],
            'removed' => $groups['removed'],
            'nextRank' => $nextRank,
        ]);
    }

    private function item(string $id): QueueItem
    {
        if (!Uuid::isValid($id)) {
            throw DomainException::notFound('Titre');
        }

        $item = $this->items->find(Uuid::fromString($id));
        if (null === $item) {
            throw DomainException::notFound('Titre');
        }

        return $item;
    }

    /**
     * @param array<string, mixed> $snapshot
     *
     * @return array<string, mixed>|null
     */
    private function findInSnapshot(array $snapshot, QueueItem $item): ?array
    {
        $id = (string) $item->getId();
        /** @var list<array<string, mixed>> $queue */
        $queue = \is_array($snapshot['queue'] ?? null) ? $snapshot['queue'] : [];
        foreach ($queue as $view) {
            if ($id === ($view['id'] ?? null)) {
                return $view;
            }
        }

        return null;
    }
}
