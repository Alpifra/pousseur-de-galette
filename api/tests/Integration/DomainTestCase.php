<?php

namespace App\Tests\Integration;

use App\Entity\Event;
use App\Entity\Guest;
use App\Entity\Host;
use App\Entity\Track;
use App\Enum\EventStatus;
use App\Service\GuestSession;
use App\Spotify\SpotifyCatalog;
use App\Tests\Double\FakeSpotifyCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;

abstract class DomainTestCase extends KernelTestCase
{
    protected EntityManagerInterface $em;
    protected FakeSpotifyCatalog $catalog;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        // No real hub nor Spotify call in the test suite
        $container->set('mercure.hub.default', new MockHub(
            'https://example.test/.well-known/mercure',
            new StaticTokenProvider('token'),
            static fn (Update $update): string => 'id',
        ));

        $this->catalog = new FakeSpotifyCatalog();
        $container->set(SpotifyCatalog::class, $this->catalog);

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $this->em = $em;
        // The ON DELETE CASCADE foreign keys empty every other table
        $this->em->getConnection()->executeStatement('DELETE FROM host');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($this->em, $this->catalog);
    }

    /**
     * The tracks the fake catalogue knows about, indexed by Spotify id.
     *
     * @param array<string, Track> $tracks
     */
    protected function fakeCatalog(array $tracks): void
    {
        $this->catalog->tracks = $tracks;
    }

    protected function createEvent(EventStatus $status = EventStatus::Live): Event
    {
        $host = new Host(\sprintf('host-%s@example.test', bin2hex(random_bytes(4))));
        $host->setPassword('x');
        $event = new Event($host, 'Soirée de test', strtoupper(bin2hex(random_bytes(3))));
        $event->setStatus($status);
        $this->em->persist($host);
        $this->em->persist($event);
        $this->em->flush();

        return $event;
    }

    protected function createGuest(Event $event, string $pseudo): Guest
    {
        $guest = new Guest($event, $pseudo, GuestSession::createToken());
        $this->em->persist($guest);
        $this->em->flush();

        return $guest;
    }

    protected function track(string $id, string $name = 'Titre', int $durationMs = 200_000, bool $explicit = false): Track
    {
        return new Track(spotifyId: $id, name: $name, artist: 'Artiste', durationMs: $durationMs, explicit: $explicit);
    }
}
