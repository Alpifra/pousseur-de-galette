<?php

namespace App\Tests\Functional;

use App\Entity\Event;
use App\Entity\Host;
use App\Entity\Track;
use App\Enum\EventStatus;
use App\Service\GuestSession;
use App\Spotify\SpotifyCatalog;
use App\Tests\Double\FakeSpotifyCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;

class GuestJourneyTest extends WebTestCase
{
    /** Spotify ids are 22 base62 characters, and the proposal endpoint checks that. */
    private const TRACK_A = 'trackDerniereDanse0001';
    private const TRACK_B = 'trackAlorsOnDanse00001';

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private FakeSpotifyCatalog $catalog;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // Rebooting the kernel between requests would drop the doubles below
        $this->client->disableReboot();
        $container = static::getContainer();

        $container->set('mercure.hub.default', new MockHub(
            'https://example.test/.well-known/mercure',
            new StaticTokenProvider('token'),
            static fn (Update $update): string => 'id',
        ));
        $this->catalog = new FakeSpotifyCatalog();
        $this->catalog->tracks = [
            self::TRACK_A => new Track(spotifyId: self::TRACK_A, name: 'Dernière danse', artist: 'Indila', durationMs: 213_000),
            self::TRACK_B => new Track(spotifyId: self::TRACK_B, name: 'Alors on danse', artist: 'Stromae', durationMs: 205_000),
        ];
        $container->set(SpotifyCatalog::class, $this->catalog);

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $this->em = $em;
        $this->em->getConnection()->executeStatement('TRUNCATE host, event, guest, queue_item, vote CASCADE');
    }

    public function testAGuestJoinsProposesVotesAndFindsTheirProposals(): void
    {
        $event = $this->createLiveEvent();

        $payload = $this->json('POST', \sprintf('/api/events/%s/join', $event->getJoinCode()), ['pseudo' => 'Alex']);
        self::assertResponseStatusCodeSame(201);
        self::assertSame('Alex', $payload['me']['pseudo']);
        self::assertSame($event->getName(), $payload['event']['name']);
        self::assertNotNull($this->client->getCookieJar()->get(GuestSession::COOKIE_NAME));

        $proposal = $this->json('POST', '/api/proposals', ['spotifyId' => self::TRACK_A]);
        self::assertResponseIsSuccessful();
        self::assertFalse($proposal['alreadyQueued']);
        self::assertSame(1, $proposal['item']['rank']);
        self::assertSame('Dernière danse', $proposal['item']['track']['name']);
        self::assertSame(1, $proposal['snapshot']['me']['pendingCount']);

        $search = $this->json('GET', '/api/search?q=danse');
        self::assertResponseIsSuccessful();
        $states = array_column($search['results'], 'state', 'spotifyId');
        self::assertSame('queued', $states[self::TRACK_A]);
        self::assertSame('available', $states[self::TRACK_B]);

        $itemId = $proposal['item']['id'];
        $vote = $this->json('PUT', \sprintf('/api/items/%s/vote', $itemId), ['value' => -1]);
        self::assertResponseIsSuccessful();
        self::assertSame(-1, $vote['item']['score']);

        $mine = $this->json('GET', '/api/my-proposals');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $mine['pending']);
        self::assertSame(1, $mine['nextRank']);
        self::assertSame('Alex', $mine['pseudo']);

        $session = $this->json('GET', '/api/session');
        self::assertSame('Alex', $session['guest']['pseudo']);

        $this->client->request('DELETE', '/api/session');
        self::assertResponseStatusCodeSame(204);
    }

    public function testAnUnknownCodeIsNotFound(): void
    {
        $payload = $this->json('GET', '/api/events/ZZZZZZ');

        self::assertResponseStatusCodeSame(404);
        self::assertSame('not_found', $payload['error']);
    }

    public function testTheQueueNeedsAGuestSession(): void
    {
        $this->createLiveEvent();

        $payload = $this->json('GET', '/api/queue');

        self::assertResponseStatusCodeSame(401);
        self::assertSame('invalid_session', $payload['error']);
    }

    public function testADraftEventCannotBeJoined(): void
    {
        $event = $this->createLiveEvent(EventStatus::Draft);

        $payload = $this->json('POST', \sprintf('/api/events/%s/join', $event->getJoinCode()), ['pseudo' => 'Alex']);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('event_not_live', $payload['error']);
    }

    public function testPseudosAreDeduplicatedWithinAnEvent(): void
    {
        $event = $this->createLiveEvent();
        $url = \sprintf('/api/events/%s/join', $event->getJoinCode());

        $first = $this->json('POST', $url, ['pseudo' => 'Alex']);
        $this->client->getCookieJar()->clear();
        $second = $this->json('POST', $url, ['pseudo' => 'Alex']);

        self::assertSame('Alex', $first['me']['pseudo']);
        self::assertSame('Alex 2', $second['me']['pseudo']);
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

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $payload;
    }

    private function createLiveEvent(EventStatus $status = EventStatus::Live): Event
    {
        $host = new Host('host@example.test');
        $host->setPassword('x');
        $event = new Event($host, 'Mariage de test', 'TESTAB');
        $event->setStatus($status);
        $this->em->persist($host);
        $this->em->persist($event);
        $this->em->flush();

        return $event;
    }
}
