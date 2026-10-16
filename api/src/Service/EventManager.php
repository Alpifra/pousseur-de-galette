<?php

namespace App\Service;

use App\Entity\Event;
use App\Entity\Guest;
use App\Entity\Host;
use App\Enum\EventStatus;
use App\Exception\DomainException;
use App\Repository\GuestRepository;
use Doctrine\ORM\EntityManagerInterface;

class EventManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private GuestRepository $guests,
        private JoinCodeGenerator $joinCodes,
        private QueueManager $queue,
        private RealtimePublisher $realtime,
    ) {
    }

    public function create(Host $host, string $name, ?\DateTimeImmutable $scheduledFor = null): Event
    {
        $event = new Event($host, $name, $this->joinCodes->generate());
        $event->setScheduledFor($scheduledFor);
        $this->em->persist($event);
        $this->em->flush();

        return $event;
    }

    public function changeStatus(Event $event, EventStatus $status): void
    {
        $event->setStatus($status);
        $this->em->flush();

        $this->realtime->publish($event, 'status', ['status' => $status->value]);
        $this->queue->broadcast($event);
    }

    public function join(Event $event, string $pseudo): Guest
    {
        if (EventStatus::Ended === $event->getStatus()) {
            throw DomainException::eventEnded();
        }

        if (EventStatus::Draft === $event->getStatus()) {
            throw DomainException::eventNotLive();
        }

        $guest = new Guest($event, $this->uniquePseudo($event, $pseudo), GuestSession::createToken());
        $this->em->persist($guest);
        $this->em->flush();

        return $guest;
    }

    private function uniquePseudo(Event $event, string $pseudo): string
    {
        $pseudo = trim(preg_replace('/\s+/u', ' ', strip_tags($pseudo)) ?? '');
        if (mb_strlen($pseudo) < 2) {
            throw new DomainException('invalid_pseudo', 'Choisis un pseudo de 2 caractères minimum.');
        }

        $pseudo = mb_substr($pseudo, 0, 36);
        $candidate = $pseudo;
        $suffix = 1;
        while (null !== $this->guests->findOneBy(['event' => $event, 'pseudo' => $candidate])) {
            $candidate = $pseudo.' '.++$suffix;
        }

        return $candidate;
    }
}
