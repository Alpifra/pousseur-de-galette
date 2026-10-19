<?php

namespace App\Tests\Functional;

use App\Entity\Event;
use App\Entity\Host;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HostQrCodeTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em = $em;
        $this->em->getConnection()->executeStatement('TRUNCATE host, event, guest, queue_item, vote CASCADE');
    }

    public function testTheHostDownloadsTheQrCodeOfTheirEvent(): void
    {
        $event = $this->createEvent('host@example.test', 'TESTAB');
        $this->client->loginUser($event->getHost());

        $this->client->request('GET', '/api/host/events/TESTAB/qr.svg');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/svg+xml');
        self::assertStringContainsString('galette-TESTAB.svg', (string) $this->client->getResponse()->headers->get('Content-Disposition'));
        self::assertStringContainsString('<svg', (string) $this->client->getResponse()->getContent());
    }

    public function testTheEventOfAnotherHostIsNotFound(): void
    {
        $this->createEvent('other@example.test', 'OTHERS');
        $mine = $this->createEvent('host@example.test', 'TESTAB');
        $this->client->loginUser($mine->getHost());

        $this->client->request('GET', '/api/host/events/OTHERS/qr.svg');

        self::assertResponseStatusCodeSame(404);
    }

    public function testTheQrCodeNeedsAHostSession(): void
    {
        $this->createEvent('host@example.test', 'TESTAB');

        $this->client->request('GET', '/api/host/events/TESTAB/qr.svg');

        self::assertResponseStatusCodeSame(401);
    }

    public function testTheHostApiExposesTheJoinUrl(): void
    {
        $event = $this->createEvent('host@example.test', 'TESTAB');
        $this->client->loginUser($event->getHost());

        $this->client->request('GET', '/api/host/me');

        /** @var array{events: list<array{joinUrl: string}>} $payload */
        $payload = json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertStringEndsWith('/e/TESTAB', $payload['events'][0]['joinUrl']);
    }

    private function createEvent(string $email, string $code): Event
    {
        $host = new Host($email);
        $host->setPassword('x');
        $event = new Event($host, 'Mariage de test', $code);
        $this->em->persist($host);
        $this->em->persist($event);
        $this->em->flush();
        // The inverse side is only populated by a fresh load, like in a real request
        $this->em->clear();

        return $event;
    }
}
