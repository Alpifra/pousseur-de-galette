<?php

namespace App\Controller;

use App\Entity\Host;
use App\Exception\DomainException;
use App\Repository\EventRepository;
use App\Service\JoinLink;
use App\Service\QrCodeRenderer;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/host/events/{code}', requirements: ['code' => '[A-Za-z0-9]{4,12}'])]
class HostEventController
{
    public function __construct(
        private Security $security,
        private EventRepository $events,
        private JoinLink $links,
        private QrCodeRenderer $qrCodes,
    ) {
    }

    #[Route('/qr.svg', name: 'api_host_event_qr', methods: ['GET'])]
    public function qrCode(string $code): Response
    {
        $event = $this->events->findOneByJoinCode($code);
        $host = $this->security->getUser();
        if (null === $event || !$host instanceof Host || $event->getHost()->getId() !== $host->getId()) {
            throw DomainException::notFound('Soirée');
        }

        $response = new Response($this->qrCodes->svg($this->links->url($event)), Response::HTTP_OK, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => \sprintf('inline; filename="galette-%s.svg"', $event->getJoinCode()),
        ]);
        $response->setPrivate();

        return $response;
    }
}
