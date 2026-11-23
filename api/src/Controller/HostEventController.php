<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Host;
use App\Entity\QueueItem;
use App\Enum\EventStatus;
use App\Exception\DomainException;
use App\Repository\EventRepository;
use App\Repository\QueueItemRepository;
use App\Service\EventManager;
use App\Service\JoinLink;
use App\Service\QrCodeRenderer;
use App\Service\QueueManager;
use App\Service\QueueSerializer;
use App\Service\RealtimePublisher;
use App\Spotify\NoActiveDeviceException;
use App\Spotify\PremiumRequiredException;
use App\Spotify\SpotifyAuthException;
use App\Spotify\SpotifyException;
use App\Spotify\SpotifyPlayer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/host/events')]
class HostEventController
{
    private const CODE = '[A-Za-z0-9]{4,12}';

    /** Settings the host can edit, with their JSON type. */
    private const SETTINGS = [
        'allowDownvotes' => 'bool',
        'rejectionThreshold' => 'int',
        'maxActiveDownvotesPerGuest' => 'int',
        'maxPendingPerGuest' => 'int',
        'replayDelayMinutes' => 'int',
        'blockExplicit' => 'bool',
        'maxTrackDurationMs' => 'int',
        'lockLeadMs' => 'int',
    ];

    public function __construct(
        private Security $security,
        private EventRepository $events,
        private QueueItemRepository $items,
        private EventManager $manager,
        private QueueManager $queue,
        private QueueSerializer $serializer,
        private RealtimePublisher $realtime,
        private SpotifyPlayer $player,
        private ValidatorInterface $validator,
        private EntityManagerInterface $em,
        private JoinLink $links,
        private QrCodeRenderer $qrCodes,
    ) {
    }

    #[Route('', name: 'api_host_event_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $body = $this->body($request);
        $event = $this->manager->create($this->host(), $this->name($body['name'] ?? null), $this->date($body['scheduledFor'] ?? null));

        return new JsonResponse($this->dashboard($event), Response::HTTP_CREATED);
    }

    #[Route('/{code}', name: 'api_host_event_show', requirements: ['code' => self::CODE], methods: ['GET'])]
    public function show(string $code): JsonResponse
    {
        return new JsonResponse($this->dashboard($this->event($code)));
    }

    #[Route('/{code}', name: 'api_host_event_update', requirements: ['code' => self::CODE], methods: ['PATCH'])]
    public function update(string $code, Request $request): JsonResponse
    {
        $event = $this->event($code);
        $body = $this->body($request);

        if (\array_key_exists('name', $body)) {
            $event->setName($this->name($body['name']));
        }
        if (\array_key_exists('scheduledFor', $body)) {
            $event->setScheduledFor($this->date($body['scheduledFor']));
        }
        if (\array_key_exists('settings', $body)) {
            $this->applySettings($event, $body['settings']);
        }
        $this->em->flush();

        if (\array_key_exists('status', $body)) {
            $status = \is_string($body['status']) ? EventStatus::tryFrom($body['status']) : null;
            if (null === $status) {
                throw new DomainException('invalid_status', 'Statut inconnu.', 400);
            }
            if (EventStatus::Ended === $event->getStatus() && EventStatus::Ended !== $status) {
                throw DomainException::eventEnded();
            }
            if ($status !== $event->getStatus()) {
                $this->manager->changeStatus($event, $status);
            }
        } else {
            // Guests see the name and some settings: they must not wait for the next change
            $this->queue->broadcast($event);
        }

        return new JsonResponse($this->dashboard($event));
    }

    #[Route('/{code}/items/{id}', name: 'api_host_item_remove', requirements: ['code' => self::CODE], methods: ['DELETE'])]
    public function removeItem(string $code, string $id): JsonResponse
    {
        $event = $this->event($code);
        $this->queue->remove($this->pendingItem($event, $id));

        return new JsonResponse($this->dashboard($event));
    }

    #[Route('/{code}/items/{id}/pin', name: 'api_host_item_pin', requirements: ['code' => self::CODE], methods: ['PUT'])]
    public function pinItem(string $code, string $id, Request $request): JsonResponse
    {
        $event = $this->event($code);
        $pinned = $this->body($request)['pinned'] ?? null;
        if (!\is_bool($pinned)) {
            throw new DomainException('invalid_pin', 'Valeur invalide.', 400);
        }

        $this->queue->pin($this->pendingItem($event, $id), $pinned);

        return new JsonResponse($this->dashboard($event));
    }

    /** The playback loop notices the new track on its next poll. */
    #[Route('/{code}/skip', name: 'api_host_event_skip', requirements: ['code' => self::CODE], methods: ['POST'])]
    public function skip(string $code): JsonResponse
    {
        $event = $this->event($code);

        try {
            $this->player->next($event->getHost(), $event->getSpotifyDeviceId());
        } catch (NoActiveDeviceException) {
            throw new DomainException('no_device', 'Aucun appareil Spotify actif : lance la lecture sur ton téléphone ou ton ordinateur.', 409);
        } catch (PremiumRequiredException) {
            throw new DomainException('premium_required', 'Le contrôle de la lecture demande un compte Spotify Premium.', 403);
        } catch (SpotifyAuthException) {
            throw DomainException::spotifyUnavailable('Ton compte Spotify est déconnecté : reconnecte-le.');
        } catch (SpotifyException $e) {
            throw DomainException::spotifyUnreachable($e);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{code}/qr.svg', name: 'api_host_event_qr', requirements: ['code' => self::CODE], methods: ['GET'])]
    public function qrCode(string $code): Response
    {
        $event = $this->event($code);

        $response = new Response($this->qrCodes->svg($this->links->url($event)), Response::HTTP_OK, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => \sprintf('inline; filename="galette-%s.svg"', $event->getJoinCode()),
        ]);
        $response->setPrivate();

        return $response;
    }

    /** @return array<string, mixed> */
    private function dashboard(Event $event): array
    {
        $settings = $event->getSettings();

        return [
            'event' => [
                'id' => (string) $event->getId(),
                'name' => $event->getName(),
                'status' => $event->getStatus()->value,
                'joinCode' => $event->getJoinCode(),
                'joinUrl' => $this->links->url($event),
                'scheduledFor' => $event->getScheduledFor()?->format('Y-m-d'),
                'device' => $event->getPlayback()->deviceName,
                'settings' => array_combine(
                    array_keys(self::SETTINGS),
                    array_map(static fn (string $name) => $settings->{$name}, array_keys(self::SETTINGS)),
                ),
            ],
            'snapshot' => $this->serializer->snapshot($event),
            'realtime' => ['hub' => $this->realtime->hubUrl(), 'topic' => $this->realtime->topicFor($event)],
        ];
    }

    private function applySettings(Event $event, mixed $values): void
    {
        if (!\is_array($values)) {
            throw new DomainException('invalid_settings', 'Paramètres invalides.', 400);
        }

        $settings = $event->getSettings();
        foreach ($values as $name => $value) {
            $type = self::SETTINGS[$name] ?? null;
            if (null === $type || ('bool' === $type ? !\is_bool($value) : !\is_int($value))) {
                throw new DomainException('invalid_settings', 'Paramètres invalides.', 400, ['field' => (string) $name]);
            }
            $settings->{$name} = $value;
        }

        $violations = $this->validator->validate($settings);
        if (\count($violations) > 0) {
            $violation = $violations->get(0);
            throw new DomainException('invalid_settings', \sprintf('Valeur refusée : %s', $violation->getMessage()), 422, ['field' => $violation->getPropertyPath()]);
        }
    }

    private function host(): Host
    {
        $host = $this->security->getUser();
        if (!$host instanceof Host) {
            throw DomainException::invalidSession();
        }

        return $host;
    }

    private function event(string $code): Event
    {
        $host = $this->host();
        $event = $this->events->findOneByJoinCode($code);
        if (null === $event || !$event->getHost()->getId()->equals($host->getId())) {
            throw DomainException::notFound('Soirée');
        }

        return $event;
    }

    /** Locked and playing tracks are already in the Spotify queue: only pending ones can still change. */
    private function pendingItem(Event $event, string $id): QueueItem
    {
        $item = Uuid::isValid($id) ? $this->items->find(Uuid::fromString($id)) : null;
        if (null === $item || !$item->getEvent()->getId()->equals($event->getId())) {
            throw DomainException::notFound('Titre');
        }
        if (!$item->getStatus()->isVotable()) {
            throw DomainException::itemLocked();
        }

        return $item;
    }

    /** @return array<string, mixed> */
    private function body(Request $request): array
    {
        try {
            $body = json_decode($request->getContent() ?: '{}', true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $body = null;
        }
        if (!\is_array($body)) {
            throw new DomainException('invalid_body', 'Requête invalide.', 400);
        }

        return $body;
    }

    private function name(mixed $name): string
    {
        $name = \is_string($name) ? trim(preg_replace('/\s+/u', ' ', $name) ?? '') : '';
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            throw new DomainException('invalid_name', 'Le nom de la soirée doit faire entre 2 et 120 caractères.');
        }

        return $name;
    }

    private function date(mixed $date): ?\DateTimeImmutable
    {
        if (null === $date || '' === $date) {
            return null;
        }

        $parsed = \is_string($date) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;
        if (false === $parsed || $parsed->format('Y-m-d') !== $date) {
            throw new DomainException('invalid_date', 'Date invalide.', 400);
        }

        return $parsed;
    }
}
