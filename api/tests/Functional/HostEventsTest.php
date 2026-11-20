<?php

namespace App\Tests\Functional;

use App\Entity\Event;
use App\Entity\Guest;
use App\Entity\Host;
use App\Entity\QueueItem;
use App\Entity\Track;
use App\Enum\EventStatus;
use App\Service\GuestSession;
use App\Spotify\NoActiveDeviceException;
use App\Spotify\SpotifyPlayer;
use App\Tests\Double\FakePlayer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;

class HostEventsTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private FakePlayer $player;
    /** @var list<array<string, mixed>> */
    private array $published = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // Rebooting the kernel between requests would drop the doubles below
        $this->client->disableReboot();
        $container = static::getContainer();

        $container->set('mercure.hub.default', new MockHub(
            'https://example.test/.well-known/mercure',
            new StaticTokenProvider('token'),
            function (Update $update): string {
                /** @var array<string, mixed> $data */
                $data = json_decode($update->getData(), true, 512, \JSON_THROW_ON_ERROR);
                $this->published[] = $data;

                return 'id';
            },
        ));
        $this->player = new FakePlayer();
        $container->set(SpotifyPlayer::class, $this->player);

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $this->em = $em;
        // The ON DELETE CASCADE foreign keys empty every other table
        $this->em->getConnection()->executeStatement('DELETE FROM host');
    }

    public function testTheHostCreatesAnEventAndFindsItInTheirList(): void
    {
        $this->client->loginUser($this->createHost('host@example.test'));

        $created = $this->json('POST', '/api/host/events', ['name' => '  Mariage   de Léa ', 'scheduledFor' => '2026-12-31']);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('Mariage de Léa', $created['event']['name']);
        self::assertSame('draft', $created['event']['status']);
        self::assertSame('2026-12-31', $created['event']['scheduledFor']);
        self::assertStringEndsWith('/e/'.$created['event']['joinCode'], $created['event']['joinUrl']);
        self::assertSame(5, $created['event']['settings']['maxPendingPerGuest']);
        self::assertSame([], $created['snapshot']['queue']);
        self::assertStringEndsWith('/events/'.$created['event']['id'], $created['realtime']['topic']);

        // The inverse side is only populated by a fresh load, like in a real request
        $this->em->clear();
        $me = $this->json('GET', '/api/host/me');
        self::assertSame([$created['event']['joinCode']], array_column($me['events'], 'joinCode'));
    }

    public function testAnEventNeedsAName(): void
    {
        $this->client->loginUser($this->createHost('host@example.test'));

        $error = $this->json('POST', '/api/host/events', ['name' => ' a ']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('invalid_name', $error['error']);
    }

    public function testTheHostOpensPausesAndClosesTheEvent(): void
    {
        $event = $this->createEvent('host@example.test', 'TESTAB', EventStatus::Draft);
        $this->client->loginUser($event->getHost());

        foreach (['live', 'paused', 'ended'] as $status) {
            $dashboard = $this->json('PATCH', '/api/host/events/TESTAB', ['status' => $status]);
            self::assertResponseIsSuccessful();
            self::assertSame($status, $dashboard['event']['status']);
        }
        self::assertContains(['type' => 'status', 'status' => 'ended'], $this->published);

        $error = $this->json('PATCH', '/api/host/events/TESTAB', ['status' => 'live']);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('event_ended', $error['error']);
    }

    public function testTheHostChangesTheSettings(): void
    {
        $event = $this->createEvent('host@example.test', 'TESTAB');
        $this->client->loginUser($event->getHost());

        $dashboard = $this->json('PATCH', '/api/host/events/TESTAB', [
            'name' => 'Nouveau nom',
            'settings' => ['allowDownvotes' => false, 'maxPendingPerGuest' => 3],
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame('Nouveau nom', $dashboard['event']['name']);
        self::assertFalse($dashboard['event']['settings']['allowDownvotes']);
        self::assertSame(3, $dashboard['snapshot']['event']['settings']['maxPendingPerGuest']);
        self::assertSame('queue', $this->published[array_key_last($this->published)]['type']);
    }

    public function testInvalidSettingsAreRejectedWithoutBeingSaved(): void
    {
        $event = $this->createEvent('host@example.test', 'TESTAB');
        $this->client->loginUser($event->getHost());

        $outOfRange = $this->json('PATCH', '/api/host/events/TESTAB', ['settings' => ['maxPendingPerGuest' => 2, 'lockLeadMs' => 1000]]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('lockLeadMs', $outOfRange['context']['field']);

        $unknown = $this->json('PATCH', '/api/host/events/TESTAB', ['settings' => ['hostApproval' => true]]);
        self::assertResponseStatusCodeSame(400);
        self::assertSame('invalid_settings', $unknown['error']);

        $dashboard = $this->json('GET', '/api/host/events/TESTAB');
        self::assertSame(5, $dashboard['event']['settings']['maxPendingPerGuest']);
    }

    public function testTheHostRemovesAndPinsPendingTracks(): void
    {
        $event = $this->createEvent('host@example.test', 'TESTAB');
        [$first, $second] = $this->queue($event, 2);
        $this->client->loginUser($event->getHost());

        $pinned = $this->json('PUT', \sprintf('/api/host/events/TESTAB/items/%s/pin', $second), ['pinned' => true]);
        self::assertResponseIsSuccessful();
        self::assertSame([$second, $first], array_column($pinned['snapshot']['queue'], 'id'));
        self::assertTrue($pinned['snapshot']['queue'][0]['pinned']);

        $removed = $this->json('DELETE', \sprintf('/api/host/events/TESTAB/items/%s', $first));
        self::assertResponseIsSuccessful();
        self::assertSame([$second], array_column($removed['snapshot']['queue'], 'id'));
    }

    public function testATrackAlreadySentToSpotifyCannotBeModerated(): void
    {
        $event = $this->createEvent('host@example.test', 'TESTAB');
        [$locked] = $this->queue($event, 1, lock: true);
        $this->client->loginUser($event->getHost());

        $error = $this->json('DELETE', \sprintf('/api/host/events/TESTAB/items/%s', $locked));

        self::assertResponseStatusCodeSame(409);
        self::assertSame('item_locked', $error['error']);
    }

    public function testTheHostSkipsTheCurrentTrack(): void
    {
        $event = $this->createEvent('host@example.test', 'TESTAB');
        $this->client->loginUser($event->getHost());

        $this->client->request('POST', '/api/host/events/TESTAB/skip');
        self::assertResponseStatusCodeSame(204);
        self::assertSame(1, $this->player->skips);

        $this->player->failure = new NoActiveDeviceException('No active device');
        $error = $this->json('POST', '/api/host/events/TESTAB/skip');
        self::assertResponseStatusCodeSame(409);
        self::assertSame('no_device', $error['error']);
    }

    public function testTheEventOfAnotherHostCannotBeManaged(): void
    {
        $other = $this->createEvent('other@example.test', 'OTHERS');
        [$item] = $this->queue($other, 1);
        $mine = $this->createEvent('host@example.test', 'TESTAB');
        $this->client->loginUser($mine->getHost());

        $this->json('GET', '/api/host/events/OTHERS');
        self::assertResponseStatusCodeSame(404);
        $this->json('PATCH', '/api/host/events/OTHERS', ['status' => 'ended']);
        self::assertResponseStatusCodeSame(404);
        $this->json('DELETE', \sprintf('/api/host/events/TESTAB/items/%s', $item));
        self::assertResponseStatusCodeSame(404);
        self::assertSame(0, $this->player->skips);
    }

    public function testTheHostApiNeedsAHostSession(): void
    {
        $this->createEvent('host@example.test', 'TESTAB');

        $this->client->request('GET', '/api/host/events/TESTAB');
        self::assertResponseStatusCodeSame(401);
        $this->client->request('POST', '/api/host/events', content: '{"name":"Soirée"}');
        self::assertResponseStatusCodeSame(401);
    }

    private function createHost(string $email): Host
    {
        $host = new Host($email);
        $host->setPassword('x');
        $this->em->persist($host);
        $this->em->flush();

        return $host;
    }

    private function createEvent(string $email, string $code, EventStatus $status = EventStatus::Live): Event
    {
        $event = new Event($this->createHost($email), 'Mariage de test', $code);
        $event->setStatus($status);
        $this->em->persist($event);
        $this->em->flush();

        return $event;
    }

    /** @return list<string> the ids of the queued items, oldest first */
    private function queue(Event $event, int $count, bool $lock = false): array
    {
        $guest = new Guest($event, 'Alex', GuestSession::createToken());
        $this->em->persist($guest);

        $ids = [];
        for ($i = 1; $i <= $count; ++$i) {
            $spotifyId = \sprintf('track%017d', $i);
            $item = new QueueItem($event, new Track(spotifyId: $spotifyId, name: 'Titre '.$i, artist: 'Artiste', durationMs: 200_000), $guest);
            if ($lock) {
                $item->lock();
            }
            $this->em->persist($item);
            $ids[] = (string) $item->getId();
            // Ties are ranked by creation date
            usleep(1000);
        }
        $this->em->flush();

        return $ids;
    }

    /**
     * @param array<string, mixed>|null $body
     *
     * @return array<string, mixed>
     */
    private function json(string $method, string $uri, ?array $body = null): array
    {
        $this->client->request(
            $method,
            $uri,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: null === $body ? null : json_encode($body, \JSON_THROW_ON_ERROR),
        );

        $content = (string) $this->client->getResponse()->getContent();
        if ('' === $content) {
            return [];
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        return $payload;
    }
}
