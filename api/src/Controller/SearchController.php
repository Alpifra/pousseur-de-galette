<?php

namespace App\Controller;

use App\Entity\Guest;
use App\Exception\DomainException;
use App\Repository\QueueItemRepository;
use App\Service\GuestSession;
use App\Spotify\SpotifyCatalog;
use App\Spotify\SpotifyException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class SearchController
{
    public function __construct(
        private SpotifyCatalog $catalog,
        private QueueItemRepository $items,
        private GuestSession $session,
    ) {
    }

    #[Route('/api/search', name: 'api_search', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $guest = $this->session->require($request);
        $query = trim((string) $request->query->get('q', ''));

        if (mb_strlen($query) < 2) {
            return new JsonResponse(['query' => $query, 'results' => [], 'pending' => $this->pending($guest)]);
        }

        try {
            $tracks = $this->catalog->search($query);
        } catch (SpotifyException $e) {
            throw DomainException::spotifyUnreachable($e);
        }

        $event = $guest->getEvent();
        $settings = $event->getSettings();
        $queued = $this->items->upcomingTrackMap($event);
        $replayed = $this->items->recentlyPlayedTrackIds($event, $settings->replayDelayMinutes);
        $rejected = $this->items->rejectedTrackIds($event);
        $pending = $this->pending($guest);

        $results = [];
        foreach ($tracks as $track) {
            $state = 'available';
            $label = null;

            if (isset($queued[$track->spotifyId])) {
                $state = 'queued';
                $label = 'Dans la file';
            } elseif (\in_array($track->spotifyId, $rejected, true)) {
                $state = 'rejected';
                $label = 'Écarté ce soir';
            } elseif (\in_array($track->spotifyId, $replayed, true)) {
                $state = 'replayed';
                $label = 'Déjà passé';
            } elseif ($track->explicit && $settings->blockExplicit) {
                $state = 'explicit';
                $label = 'Titre explicite';
            } elseif ($track->durationMs > $settings->maxTrackDurationMs) {
                $state = 'too_long';
                $label = 'Trop long';
            } elseif ($pending['count'] >= $pending['max']) {
                $state = 'quota';
                $label = 'Quota atteint';
            }

            $results[] = [
                'spotifyId' => $track->spotifyId,
                'name' => $track->name,
                'artist' => $track->artist,
                'album' => $track->album,
                'coverUrl' => $track->coverUrl,
                'durationMs' => $track->durationMs,
                'explicit' => $track->explicit,
                'state' => $state,
                'statusLabel' => $label,
                'queueItemId' => $queued[$track->spotifyId] ?? null,
            ];
        }

        return new JsonResponse(['query' => $query, 'results' => $results, 'pending' => $pending]);
    }

    /** @return array{count: int, max: int} */
    private function pending(Guest $guest): array
    {
        return [
            'count' => $this->items->countPendingForGuest($guest),
            'max' => $guest->getEvent()->getSettings()->maxPendingPerGuest,
        ];
    }
}
